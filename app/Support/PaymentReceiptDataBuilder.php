<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\Payment;
use App\Models\PaymentAllocation;

class PaymentReceiptDataBuilder
{
    /**
     * @return array{
     *     folio:string,
     *     paid_at:string,
     *     method:string,
     *     amount:float,
     *     reference:?string,
     *     tenant_name:string,
     *     tenant_email:?string,
     *     tenant_phone:?string,
     *     property_name:string,
     *     unit_name:string,
     *     allocations:array<int, array{charge_type:string, period:?string, charge_date:string, amount:float}>,
     *     allocated_total:float,
     *     credited_amount:float,
     *     pending_balance:float
     * }
     */
    public function build(Payment $payment): array
    {
        // Shared/WhatsApp links have no auth tenant context; scoped relations would
        // fail-closed (empty). Bind the payment's org for the duration of the build.
        $previousOrganizationId = TenantContext::currentOrganizationId();
        TenantContext::setOrganizationId((int) $payment->organization_id);

        try {
            $payment->loadMissing(['contract.tenant', 'contract.unit.property', 'allocations.charge']);

            $allocations = $payment->allocations
                ->map(function ($allocation): array {
                    return [
                        'charge_type' => (string) $allocation->charge?->type,
                        'period' => $allocation->charge?->period,
                        'charge_date' => DateDisplay::formatDate($allocation->charge?->charge_date, ''),
                        'amount' => (float) $allocation->amount,
                    ];
                })
                ->values()
                ->all();

            $contractId = (int) $payment->contract_id;

            return [
                'folio' => $payment->receipt_folio,
                'paid_at' => DateDisplay::formatDateTime($payment->paid_at, ''),
                'method' => $payment->method,
                'amount' => (float) $payment->amount,
                'reference' => $payment->reference,
                'tenant_name' => (string) $payment->contract?->tenant?->full_name,
                'tenant_email' => $payment->contract?->tenant?->email,
                'tenant_phone' => $payment->contract?->tenant?->phone,
                'property_name' => (string) $payment->contract?->unit?->property?->name,
                'unit_name' => (string) $payment->contract?->unit?->name,
                'allocations' => $allocations,
                'allocated_total' => (float) $payment->allocations->sum('amount'),
                'credited_amount' => (float) data_get($payment->meta, 'credited_amount', 0),
                'pending_balance' => $this->pendingBalanceForContract(
                    (int) $payment->organization_id,
                    $contractId,
                ),
            ];
        } finally {
            TenantContext::setOrganizationId($previousOrganizationId);
        }
    }

    /**
     * Same basis as contracts.show "Estado de cuenta" pending balance:
     * operational charges of the contract (excluding deposit ledger types).
     */
    private function pendingBalanceForContract(int $organizationId, int $contractId): float
    {
        if ($contractId <= 0) {
            return 0.0;
        }

        $allocationSubquery = PaymentAllocation::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->selectRaw('payment_allocations.charge_id, SUM(payment_allocations.amount) as allocated_total')
            ->groupBy('payment_allocations.charge_id');

        $charges = Charge::query()
            ->withoutOrganizationScope()
            ->where('charges.organization_id', $organizationId)
            ->where('charges.contract_id', $contractId)
            ->whereNotIn('charges.type', [
                Charge::TYPE_DEPOSIT_HOLD,
                Charge::TYPE_DEPOSIT_APPLY,
                Charge::TYPE_DEPOSIT_TRANSFER_OUT,
            ])
            ->leftJoinSub($allocationSubquery, 'alloc', function ($join): void {
                $join->on('alloc.charge_id', '=', 'charges.id');
            })
            ->select('charges.*')
            ->selectRaw('COALESCE(alloc.allocated_total, 0) as allocated_amount')
            ->get();

        $pending = $charges->sum(function (Charge $charge): float {
            $amount = round((float) $charge->amount, 2);

            if (
                $charge->type === Charge::TYPE_ADJUSTMENT
                && $amount < 0
                && (bool) data_get($charge->meta, 'settled_as_credit')
            ) {
                return 0.0;
            }

            $paid = round((float) max(min((float) $charge->allocated_amount, $amount), 0), 2);

            return round($amount - $paid, 2);
        });

        return max(0, round((float) $pending, 2));
    }
}
