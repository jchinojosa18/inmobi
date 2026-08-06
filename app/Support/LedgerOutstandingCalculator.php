<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\Payment;
use App\Models\PaymentAllocation;

/**
 * Pending / outstanding operational balances shared by cobranza, finiquito, penalties, and month close.
 *
 * Clamps each charge before summing so settled negative ADJUSTMENTs (and their CREDIT allocations)
 * are not double-counted. Remaining credit is reconstructed from ledger inflows/outflows when an
 * as-of cutoff is required; otherwise the live credit_balances row is used.
 */
class LedgerOutstandingCalculator
{
    /**
     * @var list<string>
     */
    private const EXCLUDED_TYPES = [
        Charge::TYPE_DEPOSIT_HOLD,
        Charge::TYPE_DEPOSIT_APPLY,
        Charge::TYPE_DEPOSIT_TRANSFER_OUT,
        'DEPOSIT',
        'SECURITY_DEPOSIT',
    ];

    public function __construct(
        private readonly ContractOverdueQuery $contractOverdueQuery,
    ) {}

    /**
     * Current operational outstanding for a contract (clamped pending − live credit balance).
     */
    public function outstandingForContract(Contract $contract): float
    {
        $pending = $this->clampedPendingForContract(
            organizationId: (int) $contract->organization_id,
            contractId: (int) $contract->id,
        );

        $credit = $this->liveCreditBalance(
            organizationId: (int) $contract->organization_id,
            contractId: (int) $contract->id,
        );

        return round(max($pending - $credit, 0), 2);
    }

    /**
     * Outstanding as of charge/payment cutoffs (penalties / month-close cartera).
     */
    public function outstandingForContractAsOf(
        int $organizationId,
        int $contractId,
        string $chargeDateTo,
        string $paymentPaidAtTo,
    ): float {
        $pending = $this->clampedPendingForContract(
            organizationId: $organizationId,
            contractId: $contractId,
            chargeDateTo: $chargeDateTo,
            paymentPaidAtTo: $paymentPaidAtTo,
        );

        $credit = $this->remainingCreditAsOf(
            organizationId: $organizationId,
            contractId: $contractId,
            chargeDateTo: $chargeDateTo,
            paymentPaidAtTo: $paymentPaidAtTo,
        );

        return round(max($pending - $credit, 0), 2);
    }

    /**
     * Organization cartera: per-contract outstanding (never nets excess credit of A against debt of B).
     */
    public function outstandingForOrganizationAsOf(
        int $organizationId,
        string $chargeDateTo,
        string $paymentPaidAtTo,
    ): float {
        $contractIds = Contract::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->pluck('id');

        $total = 0.0;
        foreach ($contractIds as $contractId) {
            $total = round(
                $total + $this->outstandingForContractAsOf(
                    organizationId: $organizationId,
                    contractId: (int) $contractId,
                    chargeDateTo: $chargeDateTo,
                    paymentPaidAtTo: $paymentPaidAtTo,
                ),
                2
            );
        }

        return $total;
    }

    public function clampedPendingForContract(
        int $organizationId,
        int $contractId,
        ?string $chargeDateTo = null,
        ?string $paymentPaidAtTo = null,
    ): float {
        $allocationSubquery = PaymentAllocation::query()
            ->withoutOrganizationScope()
            ->from('payment_allocations')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->where('payment_allocations.organization_id', $organizationId)
            ->where('payments.organization_id', $organizationId)
            ->whereNull('payment_allocations.deleted_at')
            ->whereNull('payments.deleted_at')
            ->when(
                $paymentPaidAtTo !== null,
                fn ($query) => $query->where('payments.paid_at', '<=', $paymentPaidAtTo)
            )
            ->selectRaw('payment_allocations.charge_id, SUM(payment_allocations.amount) as allocated_total')
            ->groupBy('payment_allocations.charge_id');

        $pendingExpression = $this->contractOverdueQuery->contractPendingAmountExpression();

        $value = Charge::query()
            ->withoutOrganizationScope()
            ->from('charges')
            ->leftJoinSub($allocationSubquery, 'alloc', function ($join): void {
                $join->on('alloc.charge_id', '=', 'charges.id');
            })
            ->where('charges.organization_id', $organizationId)
            ->where('charges.contract_id', $contractId)
            ->whereNull('charges.deleted_at')
            ->whereNotIn('charges.type', self::EXCLUDED_TYPES)
            ->when(
                $chargeDateTo !== null,
                fn ($query) => $query->whereDate('charges.charge_date', '<=', $chargeDateTo)
            )
            ->selectRaw("{$pendingExpression} as pending_balance")
            ->value('pending_balance');

        return round(max((float) $value, 0), 2);
    }

    public function remainingCreditAsOf(
        int $organizationId,
        int $contractId,
        string $chargeDateTo,
        string $paymentPaidAtTo,
    ): float {
        $creditedFromPayments = (float) Payment::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->where('contract_id', $contractId)
            ->where('method', '!=', Payment::METHOD_CREDIT)
            ->where('paid_at', '<=', $paymentPaidAtTo)
            ->get(['meta'])
            ->reduce(function (float $carry, Payment $payment): float {
                return $carry + (float) data_get($payment->meta, 'credited_amount', 0);
            }, 0.0);

        $adjustmentCredits = (float) Charge::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->where('contract_id', $contractId)
            ->where('type', Charge::TYPE_ADJUSTMENT)
            ->whereDate('charge_date', '<=', $chargeDateTo)
            ->where('amount', '<', 0)
            ->get(['amount', 'meta'])
            ->reduce(function (float $carry, Charge $charge): float {
                if (! (bool) data_get($charge->meta, 'settled_as_credit')) {
                    return $carry;
                }

                return $carry + abs((float) $charge->amount);
            }, 0.0);

        $creditApplied = (float) Payment::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->where('contract_id', $contractId)
            ->where('method', Payment::METHOD_CREDIT)
            ->where('paid_at', '<=', $paymentPaidAtTo)
            ->sum('amount');

        return round(max($creditedFromPayments + $adjustmentCredits - $creditApplied, 0), 2);
    }

    private function liveCreditBalance(int $organizationId, int $contractId): float
    {
        $balance = CreditBalance::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->where('contract_id', $contractId)
            ->value('balance');

        return round(max((float) $balance, 0), 2);
    }
}
