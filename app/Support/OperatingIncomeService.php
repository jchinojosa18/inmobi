<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\PaymentAllocation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class OperatingIncomeService
{
    /**
     * @return list<string>
     */
    public function operatingChargeTypes(): array
    {
        $configured = config('reporting.operating_income_charge_types', []);

        if (! is_array($configured) || $configured === []) {
            return [
                Charge::TYPE_RENT,
                Charge::TYPE_PENALTY,
                Charge::TYPE_SERVICE,
                Charge::TYPE_OTHER,
                Charge::TYPE_ADJUSTMENT,
            ];
        }

        return array_values(array_unique(array_map('strval', $configured)));
    }

    /**
     * @return Collection<int, array{
     *     allocation_id:int,
     *     payment_id:int,
     *     charge_id:int,
     *     paid_at:string,
     *     receipt_folio:?string,
     *     payment_method:?string,
     *     payment_reference:?string,
     *     contract_id:int,
     *     tenant_name:?string,
     *     property_name:?string,
     *     unit_name:?string,
     *     unit_code:?string,
     *     charge_type:string,
     *     charge_period:?string,
     *     allocated_amount:float
     * }>
     */
    public function allocationsForRange(int $organizationId, CarbonImmutable $dateFrom, CarbonImmutable $dateTo): Collection
    {
        $rows = PaymentAllocation::query()
            ->withoutOrganizationScope()
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->join('charges', 'charges.id', '=', 'payment_allocations.charge_id')
            ->join('contracts', 'contracts.id', '=', 'payments.contract_id')
            ->leftJoin('tenants', 'tenants.id', '=', 'contracts.tenant_id')
            ->leftJoin('units', 'units.id', '=', 'contracts.unit_id')
            ->leftJoin('properties', 'properties.id', '=', 'units.property_id')
            ->where('payment_allocations.organization_id', $organizationId)
            ->where('payments.organization_id', $organizationId)
            ->where('charges.organization_id', $organizationId)
            ->whereNull('payment_allocations.deleted_at')
            ->whereNull('payments.deleted_at')
            ->whereNull('charges.deleted_at')
            ->whereBetween('payments.paid_at', [
                $dateFrom->toDateTimeString(),
                $dateTo->toDateTimeString(),
            ])
            ->whereIn('charges.type', $this->operatingChargeTypes())
            ->select([
                'payment_allocations.id as allocation_id',
                'payment_allocations.payment_id',
                'payment_allocations.charge_id',
                'payment_allocations.amount as allocated_amount',
                'payments.paid_at',
                'payments.receipt_folio',
                'payments.method as payment_method',
                'payments.reference as payment_reference',
                'contracts.id as contract_id',
                'tenants.full_name as tenant_name',
                'properties.name as property_name',
                'units.name as unit_name',
                'units.code as unit_code',
                'charges.type as charge_type',
                'charges.period as charge_period',
            ])
            ->orderBy('payments.paid_at')
            ->orderBy('payment_allocations.id')
            ->get();

        return $rows->map(function ($row): array {
            return [
                'allocation_id' => (int) $row->allocation_id,
                'payment_id' => (int) $row->payment_id,
                'charge_id' => (int) $row->charge_id,
                'paid_at' => (string) $row->paid_at,
                'receipt_folio' => $row->receipt_folio !== null ? (string) $row->receipt_folio : null,
                'payment_method' => $row->payment_method !== null ? (string) $row->payment_method : null,
                'payment_reference' => $row->payment_reference !== null ? (string) $row->payment_reference : null,
                'contract_id' => (int) $row->contract_id,
                'tenant_name' => $row->tenant_name !== null ? (string) $row->tenant_name : null,
                'property_name' => $row->property_name !== null ? (string) $row->property_name : null,
                'unit_name' => $row->unit_name !== null ? (string) $row->unit_name : null,
                'unit_code' => $row->unit_code !== null ? (string) $row->unit_code : null,
                'charge_type' => (string) $row->charge_type,
                'charge_period' => $row->charge_period !== null ? (string) $row->charge_period : null,
                'allocated_amount' => round((float) $row->allocated_amount, 2),
            ];
        });
    }

    /**
     * @return array<string, float>
     */
    public function totalsByTypeForRange(int $organizationId, CarbonImmutable $dateFrom, CarbonImmutable $dateTo): array
    {
        $totals = array_fill_keys($this->operatingChargeTypes(), 0.0);

        $byTypeRows = PaymentAllocation::query()
            ->withoutOrganizationScope()
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->join('charges', 'charges.id', '=', 'payment_allocations.charge_id')
            ->where('payment_allocations.organization_id', $organizationId)
            ->where('payments.organization_id', $organizationId)
            ->where('charges.organization_id', $organizationId)
            ->whereNull('payment_allocations.deleted_at')
            ->whereNull('payments.deleted_at')
            ->whereNull('charges.deleted_at')
            ->whereBetween('payments.paid_at', [
                $dateFrom->toDateTimeString(),
                $dateTo->toDateTimeString(),
            ])
            ->whereIn('charges.type', $this->operatingChargeTypes())
            ->selectRaw('charges.type as charge_type, SUM(payment_allocations.amount) as total_amount')
            ->groupBy('charges.type')
            ->get();

        foreach ($byTypeRows as $row) {
            $totals[(string) $row->charge_type] = round((float) $row->total_amount, 2);
        }

        return $totals;
    }

    public function sumForRange(int $organizationId, CarbonImmutable $dateFrom, CarbonImmutable $dateTo): float
    {
        return round((float) array_sum(
            $this->totalsByTypeForRange($organizationId, $dateFrom, $dateTo)
        ), 2);
    }
}
