<?php

namespace App\Livewire\Contracts;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    private ?string $databaseDriver = null;

    public string $q = '';

    public string $status_filter = Contract::STATUS_ACTIVE;

    public string $property_id = '';

    public string $unit_id = '';

    public string $overdue_filter = 'all';

    public string $sort = 'urgency';

    public string $dir = 'asc';

    /**
     * @var array<string, array<string, string>>
     */
    protected $queryString = [
        'q' => ['except' => ''],
        'status_filter' => ['as' => 'status', 'except' => Contract::STATUS_ACTIVE],
        'property_id' => ['except' => ''],
        'unit_id' => ['except' => ''],
        'overdue_filter' => ['except' => 'all'],
        'sort' => ['except' => 'urgency'],
        'dir' => ['except' => 'asc'],
    ];

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPropertyId(): void
    {
        $this->unit_id = '';
        $this->resetPage();
    }

    public function updatingUnitId(): void
    {
        $this->resetPage();
    }

    public function updatingOverdueFilter(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        $allowed = ['urgency', 'tenant', 'unit', 'next_due', 'balance'];

        if (! in_array($field, $allowed, true)) {
            return;
        }

        if ($this->sort === $field) {
            $this->dir = $this->dir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $field;
            $this->dir = 'asc';
        }

        $this->resetPage();
    }

    public function render(): View
    {
        $properties = Property::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $units = collect();
        if ($this->property_id !== '') {
            $units = Unit::query()
                ->where('property_id', (int) $this->property_id)
                ->orderBy('name')
                ->get(['id', 'name', 'code']);
        }

        $contracts = $this->buildContractsQuery()->paginate(12);

        return view('livewire.contracts.index', [
            'contracts' => $contracts,
            'properties' => $properties,
            'units' => $units,
        ])->layout('layouts.app', [
            'title' => 'Contratos',
        ]);
    }

    private function buildContractsQuery(): Builder
    {
        $balanceSubquery = $this->balanceByContractSubquery();
        $oldestPendingRentSubquery = $this->oldestPendingRentSubquery();

        $overdueStatusSql = $this->overdueStatusSql();
        $overdueDaysSql = $this->overdueDaysSql();
        $urgencyRankSql = $this->urgencyRankSql();

        $query = Contract::query()
            ->select('contracts.*')
            ->join('units', 'units.id', '=', 'contracts.unit_id')
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->join('tenants', 'tenants.id', '=', 'contracts.tenant_id')
            ->leftJoinSub($balanceSubquery, 'balance_stats', function ($join): void {
                $join->on('balance_stats.contract_id', '=', 'contracts.id');
            })
            ->leftJoinSub($oldestPendingRentSubquery, 'rent_status', function ($join): void {
                $join->on('rent_status.contract_id', '=', 'contracts.id');
            })
            ->leftJoin('credit_balances', function ($join): void {
                $join
                    ->on('credit_balances.contract_id', '=', 'contracts.id')
                    ->whereNull('credit_balances.deleted_at');
            })
            ->addSelect([
                DB::raw('COALESCE(balance_stats.pending_balance, 0) as pending_balance'),
                DB::raw('COALESCE(credit_balances.balance, 0) as credit_balance'),
                DB::raw('rent_status.due_date as next_due_date'),
                DB::raw('rent_status.grace_until as grace_until'),
                DB::raw("{$overdueStatusSql} as overdue_status"),
                DB::raw("{$overdueDaysSql} as overdue_days"),
                DB::raw("{$urgencyRankSql} as urgency_rank"),
            ])
            ->with([
                'tenant:id,full_name,email,phone',
                'unit:id,property_id,name,code',
                'unit.property:id,name',
                'creditBalance:id,contract_id,balance',
            ]);

        $this->applyFilters($query, $overdueStatusSql);
        $this->applySorting($query, $urgencyRankSql, $overdueDaysSql);

        return $query;
    }

    private function applyFilters(Builder $query, string $overdueStatusSql): void
    {
        if ($this->q !== '') {
            $term = '%'.trim($this->q).'%';
            $query->where(function (Builder $innerQuery) use ($term): void {
                $innerQuery
                    ->where('tenants.full_name', 'like', $term)
                    ->orWhere('tenants.email', 'like', $term)
                    ->orWhere('tenants.phone', 'like', $term)
                    ->orWhere('properties.name', 'like', $term)
                    ->orWhere('units.name', 'like', $term)
                    ->orWhere('units.code', 'like', $term);
            });
        }

        if ($this->status_filter !== 'all') {
            $query->where('contracts.status', $this->status_filter);
        }

        if ($this->property_id !== '') {
            $query->where('properties.id', (int) $this->property_id);
        }

        if ($this->unit_id !== '') {
            $query->where('units.id', (int) $this->unit_id);
        }

        if ($this->overdue_filter !== 'all') {
            $allowed = ['overdue', 'grace', 'current'];
            if (in_array($this->overdue_filter, $allowed, true)) {
                $query->whereRaw("{$overdueStatusSql} = ?", [$this->overdue_filter]);
            }
        }
    }

    private function applySorting(Builder $query, string $urgencyRankSql, string $overdueDaysSql): void
    {
        $direction = strtolower($this->dir) === 'desc' ? 'desc' : 'asc';

        switch ($this->sort) {
            case 'tenant':
                $query
                    ->orderBy('tenants.full_name', $direction)
                    ->orderBy('contracts.id', 'desc');
                break;

            case 'unit':
                $query
                    ->orderBy('units.name', $direction)
                    ->orderBy('units.code', $direction)
                    ->orderBy('contracts.id', 'desc');
                break;

            case 'next_due':
                $query
                    ->orderByRaw("COALESCE(rent_status.due_date, '9999-12-31') {$direction}")
                    ->orderBy('contracts.id', 'desc');
                break;

            case 'balance':
                $query
                    ->orderByRaw("COALESCE(balance_stats.pending_balance, 0) {$direction}")
                    ->orderBy('contracts.id', 'desc');
                break;

            case 'urgency':
            default:
                $query
                    ->orderByRaw("{$urgencyRankSql} asc")
                    ->orderByRaw("{$overdueDaysSql} desc")
                    ->orderByRaw("COALESCE(rent_status.due_date, '9999-12-31') asc")
                    ->orderBy('contracts.id', 'desc');
                break;
        }
    }

    private function balanceByContractSubquery(): Builder
    {
        $allocationSubquery = PaymentAllocation::query()
            ->selectRaw('payment_allocations.charge_id, SUM(payment_allocations.amount) as allocated_total')
            ->groupBy('payment_allocations.charge_id');

        // Keep explicit include-list so future deposit-related types stay out of pending balance by default.
        $includedBalanceTypes = [
            Charge::TYPE_RENT,
            Charge::TYPE_PENALTY,
            Charge::TYPE_SERVICE,
            Charge::TYPE_DAMAGE,
            Charge::TYPE_CLEANING,
            Charge::TYPE_OTHER,
            Charge::TYPE_ADJUSTMENT,
            Charge::TYPE_MOVEOUT,
            Charge::TYPE_DEPOSIT_APPLY,
        ];

        $pendingExpression = $this->contractPendingAmountExpression();

        return Charge::query()
            ->selectRaw("charges.contract_id, {$pendingExpression} as pending_balance")
            ->leftJoinSub($allocationSubquery, 'alloc', function ($join): void {
                $join->on('alloc.charge_id', '=', 'charges.id');
            })
            ->whereIn('charges.type', $includedBalanceTypes)
            ->groupBy('charges.contract_id');
    }

    private function oldestPendingRentSubquery(): QueryBuilder
    {
        $allocationSubquery = PaymentAllocation::query()
            ->selectRaw('payment_allocations.charge_id, SUM(payment_allocations.amount) as allocated_total')
            ->groupBy('payment_allocations.charge_id');

        $dueDateExpression = $this->dueDateExpression();
        $graceDaysExpression = $this->graceDaysExpression();
        $graceUntilExpression = $this->graceUntilExpression($dueDateExpression, $graceDaysExpression);
        $pendingExpression = $this->pendingAmountExpression();

        $rankedSubquery = Charge::query()
            ->from('charges')
            ->join('contracts as c2', 'c2.id', '=', 'charges.contract_id')
            ->leftJoinSub($allocationSubquery, 'alloc', function ($join): void {
                $join->on('alloc.charge_id', '=', 'charges.id');
            })
            ->where('charges.type', Charge::TYPE_RENT)
            ->whereRaw("{$pendingExpression} > 0")
            ->selectRaw("
                charges.contract_id,
                {$dueDateExpression} as due_date,
                {$graceUntilExpression} as grace_until,
                {$pendingExpression} as pending_amount,
                ROW_NUMBER() OVER (
                    PARTITION BY charges.contract_id
                    ORDER BY {$dueDateExpression} asc, charges.id asc
                ) as row_num
            ");

        return DB::query()
            ->fromSub($rankedSubquery, 'rent_rows')
            ->selectRaw('rent_rows.contract_id, rent_rows.due_date, rent_rows.grace_until, rent_rows.pending_amount')
            ->where('rent_rows.row_num', 1);
    }

    private function overdueStatusSql(): string
    {
        $todayExpression = $this->todayExpression();

        return "CASE
            WHEN rent_status.contract_id IS NULL THEN 'current'
            WHEN {$todayExpression} > rent_status.grace_until THEN 'overdue'
            WHEN {$todayExpression} >= rent_status.due_date AND {$todayExpression} <= rent_status.grace_until THEN 'grace'
            ELSE 'current'
        END";
    }

    private function overdueDaysSql(): string
    {
        $todayExpression = $this->todayExpression();
        $overdueDaysExpression = $this->overdueDaysExpression();

        return "CASE
            WHEN rent_status.contract_id IS NULL THEN 0
            WHEN {$todayExpression} > rent_status.grace_until THEN {$overdueDaysExpression}
            ELSE 0
        END";
    }

    private function urgencyRankSql(): string
    {
        $todayExpression = $this->todayExpression();

        return "CASE
            WHEN rent_status.contract_id IS NULL THEN 3
            WHEN {$todayExpression} > rent_status.grace_until THEN 1
            WHEN {$todayExpression} >= rent_status.due_date AND {$todayExpression} <= rent_status.grace_until THEN 2
            ELSE 3
        END";
    }

    private function dueDateExpression(): string
    {
        if ($this->databaseDriver() === 'sqlite') {
            return "COALESCE(charges.due_date, date(json_extract(charges.meta, '$.due_date')), charges.charge_date)";
        }

        return "COALESCE(charges.due_date, CAST(JSON_UNQUOTE(JSON_EXTRACT(charges.meta, '$.due_date')) AS DATE), charges.charge_date)";
    }

    private function graceDaysExpression(): string
    {
        if ($this->databaseDriver() === 'sqlite') {
            return "COALESCE(CAST(json_extract(charges.meta, '$.grace_days') AS INTEGER), c2.grace_days, 0)";
        }

        return "COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(charges.meta, '$.grace_days')) AS UNSIGNED), c2.grace_days, 0)";
    }

    private function graceUntilExpression(string $dueDateExpression, string $graceDaysExpression): string
    {
        if ($this->databaseDriver() === 'sqlite') {
            return "COALESCE(charges.grace_until, date({$dueDateExpression}, '+' || {$graceDaysExpression} || ' day'))";
        }

        return "COALESCE(charges.grace_until, DATE_ADD({$dueDateExpression}, INTERVAL {$graceDaysExpression} DAY))";
    }

    private function todayExpression(): string
    {
        return $this->databaseDriver() === 'sqlite' ? "date('now')" : 'CURDATE()';
    }

    private function overdueDaysExpression(): string
    {
        if ($this->databaseDriver() === 'sqlite') {
            return "CAST(julianday(date('now')) - julianday(rent_status.grace_until) AS INTEGER)";
        }

        return 'DATEDIFF(CURDATE(), rent_status.grace_until)';
    }

    private function databaseDriver(): string
    {
        if ($this->databaseDriver === null) {
            $this->databaseDriver = DB::connection()->getDriverName();
        }

        return $this->databaseDriver;
    }

    private function pendingAmountExpression(): string
    {
        $rawPending = $this->rawPendingAmountExpression();

        if ($this->databaseDriver() === 'sqlite') {
            return "MAX({$rawPending}, 0)";
        }

        return "GREATEST({$rawPending}, 0)";
    }

    private function rawPendingAmountExpression(): string
    {
        return 'charges.amount - COALESCE(alloc.allocated_total, 0)';
    }

    private function contractPendingAmountExpression(): string
    {
        $rawPending = $this->rawPendingAmountExpression();

        if ($this->databaseDriver() === 'sqlite') {
            return "MAX(SUM({$rawPending}), 0)";
        }

        return "GREATEST(SUM({$rawPending}), 0)";
    }
}
