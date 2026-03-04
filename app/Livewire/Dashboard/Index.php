<?php

namespace App\Livewire\Dashboard;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Unit;
use App\Support\OperatingIncomeService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Index extends Component
{
    private ?string $databaseDriver = null;

    public function render(OperatingIncomeService $operatingIncomeService): View
    {
        $now = CarbonImmutable::now('America/Tijuana');
        $todayDate = $now->toDateString();
        $monthStart = $now->startOfMonth()->startOfDay();
        $monthEnd = $now->endOfDay();

        $organizationId = (int) auth()->user()?->organization_id;
        $incomeMonth = $operatingIncomeService->sumForRange($organizationId, $monthStart, $monthEnd);
        $expenseMonth = (float) Expense::query()
            ->whereBetween('spent_at', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount');

        $netMonth = round($incomeMonth - $expenseMonth, 2);

        $overdueStatusSql = $this->overdueStatusSql($todayDate);
        $overdueDaysSql = $this->overdueDaysSql($todayDate);

        $overduePortfolioTotal = $this->overduePortfolioTotal($todayDate, $overdueStatusSql);
        $activeContracts = Contract::query()
            ->where('status', Contract::STATUS_ACTIVE)
            ->count();

        $activeUnits = Unit::query()
            ->where('status', 'active')
            ->count();
        $occupiedUnits = (int) Contract::query()
            ->where('status', Contract::STATUS_ACTIVE)
            ->distinct('unit_id')
            ->count('unit_id');
        $availableUnits = max($activeUnits - $occupiedUnits, 0);

        $overdueContracts = $this->contractsByStatus($todayDate, $overdueStatusSql, $overdueDaysSql, 'overdue');
        $graceContracts = $this->contractsByStatus($todayDate, $overdueStatusSql, $overdueDaysSql, 'grace');
        $recentPayments = $this->recentPayments();

        return view('livewire.dashboard.index', [
            'incomeMonth' => $incomeMonth,
            'expenseMonth' => $expenseMonth,
            'netMonth' => $netMonth,
            'overduePortfolioTotal' => $overduePortfolioTotal,
            'activeContracts' => $activeContracts,
            'occupiedUnits' => $occupiedUnits,
            'availableUnits' => $availableUnits,
            'overdueContracts' => $overdueContracts,
            'graceContracts' => $graceContracts,
            'recentPayments' => $recentPayments,
        ])->layout('layouts.app', [
            'title' => 'Dashboard operativo',
        ]);
    }

    /**
     * @return Collection<int, object>
     */
    private function contractsByStatus(
        string $todayDate,
        string $overdueStatusSql,
        string $overdueDaysSql,
        string $status
    ): Collection {
        $query = $this->contractsLedgerBaseQuery($todayDate, $overdueStatusSql, $overdueDaysSql)
            ->whereRaw("{$overdueStatusSql} = ?", [$status]);

        if ($status === 'overdue') {
            $query->orderByRaw("{$overdueDaysSql} desc")
                ->orderByRaw('COALESCE(balance_stats.pending_balance, 0) desc');
        } else {
            $query->orderByRaw('COALESCE(rent_status.due_date, contracts.starts_at) asc')
                ->orderByRaw('COALESCE(balance_stats.pending_balance, 0) desc');
        }

        return $query
            ->limit(10)
            ->get();
    }

    private function overduePortfolioTotal(string $todayDate, string $overdueStatusSql): float
    {
        $total = $this->contractsLedgerBaseQuery($todayDate, $overdueStatusSql, $this->overdueDaysSql($todayDate))
            ->whereRaw("{$overdueStatusSql} = 'overdue'")
            ->sum(DB::raw('COALESCE(balance_stats.pending_balance, 0)'));

        return round((float) $total, 2);
    }

    private function contractsLedgerBaseQuery(string $todayDate, string $overdueStatusSql, string $overdueDaysSql): Builder
    {
        $balanceSubquery = $this->balanceByContractSubquery();
        $oldestPendingRentSubquery = $this->oldestPendingRentSubquery();

        return Contract::query()
            ->select([
                'contracts.id as contract_id',
                'contracts.status as contract_status',
                'tenants.full_name as tenant_name',
                'tenants.email as tenant_email',
                'tenants.phone as tenant_phone',
                'properties.name as property_name',
                'units.name as unit_name',
                'units.code as unit_code',
            ])
            ->where('contracts.status', Contract::STATUS_ACTIVE)
            ->join('units', 'units.id', '=', 'contracts.unit_id')
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->join('tenants', 'tenants.id', '=', 'contracts.tenant_id')
            ->leftJoinSub($balanceSubquery, 'balance_stats', function ($join): void {
                $join->on('balance_stats.contract_id', '=', 'contracts.id');
            })
            ->leftJoinSub($oldestPendingRentSubquery, 'rent_status', function ($join): void {
                $join->on('rent_status.contract_id', '=', 'contracts.id');
            })
            ->whereColumn('units.organization_id', 'contracts.organization_id')
            ->whereColumn('properties.organization_id', 'contracts.organization_id')
            ->whereColumn('tenants.organization_id', 'contracts.organization_id')
            ->addSelect([
                DB::raw('COALESCE(balance_stats.pending_balance, 0) as pending_balance'),
                DB::raw('rent_status.due_date as due_date'),
                DB::raw('rent_status.grace_until as grace_until'),
                DB::raw("{$overdueStatusSql} as overdue_status"),
                DB::raw("{$overdueDaysSql} as overdue_days"),
            ]);
    }

    private function balanceByContractSubquery(): Builder
    {
        $allocationSubquery = PaymentAllocation::query()
            ->selectRaw('payment_allocations.charge_id, SUM(payment_allocations.amount) as allocated_total')
            ->groupBy('payment_allocations.charge_id');

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

    private function oldestPendingRentSubquery(): \Illuminate\Database\Query\Builder
    {
        $allocationSubquery = PaymentAllocation::query()
            ->selectRaw('payment_allocations.charge_id, SUM(payment_allocations.amount) as allocated_total')
            ->groupBy('payment_allocations.charge_id');

        $dueDateExpression = $this->dueDateExpression();
        $graceUntilExpression = $this->graceUntilExpression($dueDateExpression);
        $pendingExpression = $this->pendingAmountExpression();

        $rankedSubquery = Charge::query()
            ->from('charges')
            ->leftJoinSub($allocationSubquery, 'alloc', function ($join): void {
                $join->on('alloc.charge_id', '=', 'charges.id');
            })
            ->where('charges.type', Charge::TYPE_RENT)
            ->whereRaw("{$pendingExpression} > 0")
            ->selectRaw("
                charges.contract_id,
                {$dueDateExpression} as due_date,
                {$graceUntilExpression} as grace_until,
                ROW_NUMBER() OVER (
                    PARTITION BY charges.contract_id
                    ORDER BY {$dueDateExpression} asc, charges.id asc
                ) as row_num
            ");

        return DB::query()
            ->fromSub($rankedSubquery, 'rent_rows')
            ->selectRaw('rent_rows.contract_id, rent_rows.due_date, rent_rows.grace_until')
            ->where('rent_rows.row_num', 1);
    }

    /**
     * @return Collection<int, object>
     */
    private function recentPayments(): Collection
    {
        $allocationSubquery = PaymentAllocation::query()
            ->selectRaw('payment_allocations.payment_id, SUM(payment_allocations.amount) as allocated_total')
            ->groupBy('payment_allocations.payment_id');

        return Payment::query()
            ->select([
                'payments.id as payment_id',
                'payments.receipt_folio',
                'payments.paid_at',
                'payments.amount',
                'payments.contract_id',
                'tenants.full_name as tenant_name',
                'properties.name as property_name',
                'units.name as unit_name',
                'units.code as unit_code',
                DB::raw('COALESCE(payment_stats.allocated_total, 0) as allocated_total'),
            ])
            ->join('contracts', 'contracts.id', '=', 'payments.contract_id')
            ->join('tenants', 'tenants.id', '=', 'contracts.tenant_id')
            ->join('units', 'units.id', '=', 'contracts.unit_id')
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->leftJoinSub($allocationSubquery, 'payment_stats', function ($join): void {
                $join->on('payment_stats.payment_id', '=', 'payments.id');
            })
            ->whereColumn('contracts.organization_id', 'payments.organization_id')
            ->whereColumn('tenants.organization_id', 'payments.organization_id')
            ->whereColumn('units.organization_id', 'payments.organization_id')
            ->whereColumn('properties.organization_id', 'payments.organization_id')
            ->orderByDesc('payments.paid_at')
            ->limit(10)
            ->get();
    }

    private function overdueStatusSql(string $todayDate): string
    {
        $todayLiteral = "'{$todayDate}'";

        return "CASE
            WHEN rent_status.contract_id IS NULL THEN 'current'
            WHEN {$todayLiteral} > rent_status.grace_until THEN 'overdue'
            WHEN {$todayLiteral} >= rent_status.due_date AND {$todayLiteral} <= rent_status.grace_until THEN 'grace'
            ELSE 'current'
        END";
    }

    private function overdueDaysSql(string $todayDate): string
    {
        $todayLiteral = "'{$todayDate}'";
        $overdueDiffExpression = $this->overdueDiffExpression($todayDate);

        return "CASE
            WHEN rent_status.contract_id IS NULL THEN 0
            WHEN {$todayLiteral} > rent_status.grace_until THEN {$overdueDiffExpression}
            ELSE 0
        END";
    }

    private function overdueDiffExpression(string $todayDate): string
    {
        if ($this->databaseDriver() === 'sqlite') {
            return "CAST(julianday('{$todayDate}') - julianday(rent_status.grace_until) AS INTEGER)";
        }

        return "DATEDIFF('{$todayDate}', rent_status.grace_until)";
    }

    private function dueDateExpression(): string
    {
        return 'COALESCE(charges.due_date, charges.charge_date)';
    }

    private function graceUntilExpression(string $dueDateExpression): string
    {
        return "COALESCE(charges.grace_until, {$dueDateExpression})";
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
