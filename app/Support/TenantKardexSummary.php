<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Tenant;
use Illuminate\Support\Collection;

final class TenantKardexSummary
{
    /** @var list<int> */
    private array $contractIds;

    private int $organizationId;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly ?string $kardexReturnUrl = null,
    ) {
        $this->organizationId = (int) $tenant->organization_id;
        $this->contractIds = Contract::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $this->organizationId)
            ->where('tenant_id', $tenant->id)
            ->pluck('id')
            ->all();
    }

    public static function for(Tenant $tenant, ?string $kardexReturnUrl = null): self
    {
        return new self($tenant, $kardexReturnUrl);
    }

    public function activeContractsCount(): int
    {
        return (int) Contract::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $this->organizationId)
            ->where('tenant_id', $this->tenant->id)
            ->where('status', Contract::STATUS_ACTIVE)
            ->count();
    }

    public function pendingBalance(): float
    {
        return round((float) $this->outstandingCharges()->sum('balance'), 2);
    }

    public function creditBalance(): float
    {
        if ($this->contractIds === []) {
            return 0.0;
        }

        return round((float) CreditBalance::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $this->organizationId)
            ->whereIn('contract_id', $this->contractIds)
            ->sum('balance'), 2);
    }

    public function totalPaid(): float
    {
        if ($this->contractIds === []) {
            return 0.0;
        }

        return round((float) Payment::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $this->organizationId)
            ->whereIn('contract_id', $this->contractIds)
            ->sum('amount'), 2);
    }

    /**
     * @return Collection<int, array{
     *     id:int,
     *     status:string,
     *     rent_amount:float,
     *     starts_at:?string,
     *     ends_at:?string,
     *     unit_label:string,
     *     show_url:string
     * }>
     */
    public function contracts(): Collection
    {
        return Contract::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $this->organizationId)
            ->where('tenant_id', $this->tenant->id)
            ->with(['unit.property'])
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (Contract $contract): array {
                $propertyName = $contract->unit?->property?->name ?? '';
                $unitName = $contract->unit?->name ?? '';
                $unitLabel = trim(($propertyName !== '' ? $propertyName.' / ' : '').$unitName);

                return [
                    'id' => $contract->id,
                    'status' => $contract->status,
                    'rent_amount' => round((float) $contract->rent_amount, 2),
                    'starts_at' => DateDisplay::formatDate($contract->starts_at, ''),
                    'ends_at' => DateDisplay::formatDate($contract->ends_at, ''),
                    'unit_label' => $unitLabel !== '' ? $unitLabel : __('common.n_a'),
                    'show_url' => $this->urlBackToKardex(route('contracts.show', $contract)),
                ];
            });
    }

    /**
     * @return Collection<int, array{
     *     contract_id:int,
     *     unit_label:string,
     *     type:string,
     *     charge_date:?string,
     *     amount:float,
     *     paid:float,
     *     balance:float,
     *     contract_show_url:string
     * }>
     */
    public function outstandingCharges(): Collection
    {
        if ($this->contractIds === []) {
            return collect();
        }

        $allocationSubquery = PaymentAllocation::query()
            ->withoutOrganizationScope()
            ->where('payment_allocations.organization_id', $this->organizationId)
            ->selectRaw('payment_allocations.charge_id, SUM(payment_allocations.amount) as allocated_total')
            ->groupBy('payment_allocations.charge_id');

        $charges = Charge::query()
            ->withoutOrganizationScope()
            ->where('charges.organization_id', $this->organizationId)
            ->whereIn('charges.contract_id', $this->contractIds)
            ->whereNotIn('charges.type', [Charge::TYPE_DEPOSIT_HOLD, Charge::TYPE_DEPOSIT_APPLY])
            ->with(['contract.unit.property'])
            ->leftJoinSub($allocationSubquery, 'alloc', function ($join): void {
                $join->on('alloc.charge_id', '=', 'charges.id');
            })
            ->select('charges.*')
            ->selectRaw('COALESCE(alloc.allocated_total, 0) as allocated_amount')
            ->orderByDesc('charges.charge_date')
            ->orderByDesc('charges.id')
            ->get();

        return $charges
            ->map(function (Charge $charge): array {
                $amount = round((float) $charge->amount, 2);
                $paid = round((float) max(min((float) $charge->allocated_amount, $amount), 0), 2);
                $balance = round($amount - $paid, 2);
                $unitName = $charge->contract?->unit?->name ?? '';
                $unitLabel = trim('#'.$charge->contract_id.' · '.$unitName, ' ·');

                return [
                    'contract_id' => (int) $charge->contract_id,
                    'unit_label' => $unitLabel !== '' ? $unitLabel : '#'.$charge->contract_id,
                    'type' => $charge->type,
                    'charge_date' => DateDisplay::formatDate($charge->charge_date, ''),
                    'amount' => $amount,
                    'paid' => $paid,
                    'balance' => $balance,
                    'contract_show_url' => $this->urlBackToKardex(route('contracts.show', $charge->contract_id)),
                ];
            })
            ->filter(fn (array $row): bool => $row['balance'] > 0)
            ->values();
    }

    /**
     * @return Collection<int, array{
     *     id:int,
     *     folio:?string,
     *     paid_at:?string,
     *     method:?string,
     *     amount:float,
     *     contract_id:int,
     *     show_url:string
     * }>
     */
    public function recentPayments(int $limit = 15): Collection
    {
        if ($this->contractIds === []) {
            return collect();
        }

        return Payment::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $this->organizationId)
            ->whereIn('contract_id', $this->contractIds)
            ->latest('paid_at')
            ->limit($limit)
            ->get()
            ->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'folio' => $payment->receipt_folio,
                'paid_at' => DateDisplay::formatDateTime($payment->paid_at, ''),
                'method' => $payment->method,
                'amount' => round((float) $payment->amount, 2),
                'contract_id' => (int) $payment->contract_id,
                'show_url' => $this->urlBackToKardex(route('payments.show', $payment)),
            ]);
    }

    private function urlBackToKardex(string $destinationUrl): string
    {
        $returnUrl = NavigationReturn::sanitizeUrl($this->kardexReturnUrl)
            ?? route('tenants.show', $this->tenant, false);

        return NavigationReturn::append(
            $destinationUrl,
            $returnUrl,
            __('catalog.tenants.kardex.back_to_tenant', ['name' => $this->tenant->full_name]),
        );
    }
}
