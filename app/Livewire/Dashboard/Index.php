<?php

namespace App\Livewire\Dashboard;

use App\Actions\Charges\GenerateMonthlyRentChargesAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\MonthCloseGuard;
use App\Support\OperatingIncomeService;
use App\Support\OrganizationSettingsService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class Index extends Component
{
    private ?string $databaseDriver = null;

    #[On('payment-registered')]
    public function onPaymentRegistered(): void {}

    #[On('expense-created')]
    public function onExpenseCreated(): void {}

    public function mount(): void
    {
        if (! (auth()->user()?->can('dashboard.view') ?? false)) {
            abort(403);
        }
    }

    public function dismissOnboarding(OrganizationSettingsService $settingsService): void
    {
        $organizationId = (int) auth()->user()?->organization_id;

        if ($organizationId <= 0) {
            return;
        }

        $dismissedUntil = $settingsService->dismissOnboardingForDays($organizationId, 7);

        session()->flash(
            'success',
            'Checklist oculto hasta '.$dismissedUntil->timezone('America/Tijuana')->format('Y-m-d').'.'
        );
    }

    public function generateCurrentMonthRent(GenerateMonthlyRentChargesAction $action): void
    {
        if (! (auth()->user()?->can('rents.generate') ?? false)) {
            abort(403);
        }

        $organizationId = (int) auth()->user()?->organization_id;

        if ($organizationId <= 0) {
            return;
        }

        $currentMonth = CarbonImmutable::now('America/Tijuana')->format('Y-m');

        if (MonthCloseGuard::isMonthClosed($organizationId, $currentMonth)) {
            session()->flash('error', "El mes {$currentMonth} está cerrado. No se pueden generar rentas.");

            return;
        }

        $result = $action->executeForOrganization($currentMonth, $organizationId);

        session()->flash(
            'success',
            "Rentas del {$currentMonth}: creadas={$result['created']} omitidas={$result['skipped']}."
        );
    }

    public function render(
        OperatingIncomeService $operatingIncomeService,
        OrganizationSettingsService $settingsService
    ): View {
        $now = CarbonImmutable::now('America/Tijuana');
        $todayDate = $now->toDateString();
        $monthStart = $now->startOfMonth()->startOfDay();
        $monthEnd = $now->endOfDay();

        $organizationId = (int) auth()->user()?->organization_id;
        $currentPlazaId = TenantContext::currentPlazaId();

        $incomeMonth = $operatingIncomeService->sumForRange($organizationId, $monthStart, $monthEnd, $currentPlazaId);
        $expenseMonth = (float) Expense::query()
            ->when($currentPlazaId !== null, function (Builder $query) use ($currentPlazaId): void {
                $query->whereHas('unit.property', function (Builder $propertyQuery) use ($currentPlazaId): void {
                    $propertyQuery->where('plaza_id', $currentPlazaId);
                });
            })
            ->whereBetween('spent_at', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount');

        $netMonth = round($incomeMonth - $expenseMonth, 2);

        $overdueStatusSql = $this->overdueStatusSql($todayDate);
        $overdueDaysSql = $this->overdueDaysSql($todayDate);

        $overduePortfolioTotal = $this->overduePortfolioTotal($todayDate, $overdueStatusSql, $currentPlazaId);
        $activeContracts = Contract::query()
            ->join('units', 'units.id', '=', 'contracts.unit_id')
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->where('contracts.status', Contract::STATUS_ACTIVE)
            ->when($currentPlazaId !== null, function (Builder $query) use ($currentPlazaId): void {
                $query->where('properties.plaza_id', $currentPlazaId);
            })
            ->count();

        $activeUnits = Unit::query()
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->where('units.status', 'active')
            ->when($currentPlazaId !== null, function (Builder $query) use ($currentPlazaId): void {
                $query->where('properties.plaza_id', $currentPlazaId);
            })
            ->count('units.id');
        $occupiedUnits = (int) Contract::query()
            ->join('units', 'units.id', '=', 'contracts.unit_id')
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->where('contracts.status', Contract::STATUS_ACTIVE)
            ->when($currentPlazaId !== null, function (Builder $query) use ($currentPlazaId): void {
                $query->where('properties.plaza_id', $currentPlazaId);
            })
            ->distinct('unit_id')
            ->count('contracts.unit_id');
        $availableUnits = max($activeUnits - $occupiedUnits, 0);

        $overdueContracts = $this->contractsByStatus(
            $todayDate,
            $overdueStatusSql,
            $overdueDaysSql,
            'overdue',
            $currentPlazaId
        );
        $graceContracts = $this->contractsByStatus(
            $todayDate,
            $overdueStatusSql,
            $overdueDaysSql,
            'grace',
            $currentPlazaId
        );
        $recentPayments = $this->recentPayments($currentPlazaId);
        $onboardingChecklist = $this->buildOnboardingChecklist($organizationId, $now, $settingsService);

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
            'onboardingChecklist' => $onboardingChecklist,
            'canCreatePayments' => auth()->user()?->can('payments.create') ?? false,
            'canCreateExpenses' => auth()->user()?->can('expenses.create') ?? false,
            'canManageContracts' => auth()->user()?->can('contracts.manage') ?? false,
            'canGenerateRents' => auth()->user()?->can('rents.generate') ?? false,
        ])->layout('layouts.app', [
            'title' => 'Dashboard operativo',
        ]);
    }

    /**
     * @return array{
     *     show:bool,
     *     current_month:string,
     *     critical_completed:int,
     *     critical_total:int,
     *     critical_progress_percent:int,
     *     critical_steps:list<array{
     *         key:string,
     *         title:string,
     *         description:string,
     *         complete:bool,
     *         ctas:list<array{type:string,label:string,route?:string}>
     *     }>,
     *     recommended_steps:list<array{
     *         key:string,
     *         title:string,
     *         description:string,
     *         complete:bool,
     *         ctas:list<array{type:string,label:string,route?:string}>
     *     }>
     * }
     */
    private function buildOnboardingChecklist(
        int $organizationId,
        CarbonImmutable $now,
        OrganizationSettingsService $settingsService
    ): array {
        $currentMonth = $now->format('Y-m');

        $propertiesCount = Property::query()->count();
        $unitsCount = Unit::query()->count();
        $tenantsCount = Tenant::query()->count();
        $activeContractsCount = Contract::query()
            ->where('status', Contract::STATUS_ACTIVE)
            ->count();
        $rentChargesCurrentMonthCount = Charge::query()
            ->where('type', Charge::TYPE_RENT)
            ->where('period', $currentMonth)
            ->whereHas('contract', fn (Builder $query): Builder => $query->where('status', Contract::STATUS_ACTIVE))
            ->count();
        $paymentsCount = Payment::query()->count();
        $expensesCount = Expense::query()->count();

        $criticalSteps = [
            [
                'key' => 'properties',
                'title' => 'Crear propiedad o casa',
                'description' => 'Registra tu primer inmueble para empezar a operar.',
                'complete' => $propertiesCount > 0,
                'ctas' => [
                    ['type' => 'route', 'label' => 'Ir a propiedades', 'route' => 'properties.index'],
                    ['type' => 'route', 'label' => 'Nueva casa', 'route' => 'houses.create'],
                ],
            ],
            [
                'key' => 'units',
                'title' => 'Crear unidades',
                'description' => 'Define unidades ocupables para poder contratar.',
                'complete' => $unitsCount > 0,
                'ctas' => [
                    ['type' => 'route', 'label' => 'Gestionar unidades', 'route' => 'properties.index'],
                ],
            ],
            [
                'key' => 'tenants',
                'title' => 'Crear inquilinos',
                'description' => 'Captura al menos un inquilino activo.',
                'complete' => $tenantsCount > 0,
                'ctas' => [
                    ['type' => 'route', 'label' => 'Ir a inquilinos', 'route' => 'tenants.index'],
                ],
            ],
            [
                'key' => 'contracts',
                'title' => 'Crear contratos activos',
                'description' => 'Necesitas un contrato activo para generar rentas y cobranza.',
                'complete' => $activeContractsCount > 0,
                'ctas' => [
                    ['type' => 'route', 'label' => 'Nuevo contrato', 'route' => 'contracts.create'],
                ],
            ],
            [
                'key' => 'rent_charges',
                'title' => 'Generar o confirmar rentas del mes',
                'description' => "Valida que existan cargos RENT para {$currentMonth}.",
                'complete' => $rentChargesCurrentMonthCount > 0,
                'ctas' => [
                    ['type' => 'action_generate_rent', 'label' => 'Generar rentas del mes'],
                ],
            ],
        ];

        $recommendedSteps = [
            [
                'key' => 'payments',
                'title' => 'Registrar primer pago',
                'description' => 'Recomendado para validar recibo, allocation y cobranza.',
                'complete' => $paymentsCount > 0,
                'ctas' => [
                    ['type' => 'action_open_quick_payment', 'label' => 'Registrar pago'],
                ],
            ],
            [
                'key' => 'expenses',
                'title' => 'Registrar primer egreso',
                'description' => 'Recomendado para validar reporte de flujo y neto.',
                'complete' => $expensesCount > 0,
                'ctas' => [
                    ['type' => 'action_open_quick_expense', 'label' => 'Registrar egreso'],
                ],
            ],
        ];

        $can = fn (string $permission): bool => auth()->user()?->can($permission) ?? false;

        $criticalSteps = collect($criticalSteps)
            ->map(function (array $step) use ($can): array {
                $step['ctas'] = collect($step['ctas'])
                    ->filter(function (array $cta) use ($can): bool {
                        return match ((string) ($cta['type'] ?? '')) {
                            'route' => match ((string) ($cta['route'] ?? '')) {
                                'properties.index' => $can('properties.view'),
                                'houses.create' => $can('properties.manage'),
                                'tenants.index' => $can('tenants.view'),
                                'contracts.create' => $can('contracts.manage'),
                                default => true,
                            },
                            'action_generate_rent' => $can('rents.generate'),
                            default => true,
                        };
                    })
                    ->values()
                    ->all();

                return $step;
            })
            ->values()
            ->all();

        $recommendedSteps = collect($recommendedSteps)
            ->map(function (array $step) use ($can): array {
                $step['ctas'] = collect($step['ctas'])
                    ->filter(function (array $cta) use ($can): bool {
                        return match ((string) ($cta['type'] ?? '')) {
                            'action_open_quick_payment' => $can('payments.create'),
                            'action_open_quick_expense' => $can('expenses.create'),
                            default => true,
                        };
                    })
                    ->values()
                    ->all();

                return $step;
            })
            ->values()
            ->all();

        $criticalCompleted = collect($criticalSteps)
            ->filter(fn (array $step): bool => (bool) $step['complete'])
            ->count();
        $criticalTotal = count($criticalSteps);
        $criticalProgressPercent = $criticalTotal > 0
            ? (int) round(($criticalCompleted / $criticalTotal) * 100)
            : 0;
        $criticalReady = $criticalCompleted === $criticalTotal;
        $dismissed = $settingsService->isOnboardingDismissed($organizationId, $now);

        return [
            'show' => ! $criticalReady && ! $dismissed,
            'current_month' => $currentMonth,
            'critical_completed' => $criticalCompleted,
            'critical_total' => $criticalTotal,
            'critical_progress_percent' => $criticalProgressPercent,
            'critical_steps' => $criticalSteps,
            'recommended_steps' => $recommendedSteps,
        ];
    }

    /**
     * @return Collection<int, object>
     */
    private function contractsByStatus(
        string $todayDate,
        string $overdueStatusSql,
        string $overdueDaysSql,
        string $status,
        ?int $currentPlazaId
    ): Collection {
        $query = $this->contractsLedgerBaseQuery($todayDate, $overdueStatusSql, $overdueDaysSql, $currentPlazaId)
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

    private function overduePortfolioTotal(string $todayDate, string $overdueStatusSql, ?int $currentPlazaId): float
    {
        $total = $this->contractsLedgerBaseQuery(
            $todayDate,
            $overdueStatusSql,
            $this->overdueDaysSql($todayDate),
            $currentPlazaId
        )
            ->whereRaw("{$overdueStatusSql} = 'overdue'")
            ->sum(DB::raw('COALESCE(balance_stats.pending_balance, 0)'));

        return round((float) $total, 2);
    }

    private function contractsLedgerBaseQuery(
        string $todayDate,
        string $overdueStatusSql,
        string $overdueDaysSql,
        ?int $currentPlazaId
    ): Builder {
        $balanceSubquery = $this->balanceByContractSubquery();
        $oldestPendingRentSubquery = $this->oldestPendingRentSubquery();

        $query = Contract::query()
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

        if ($currentPlazaId !== null) {
            $query->where('properties.plaza_id', $currentPlazaId);
        }

        return $query;
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
    private function recentPayments(?int $currentPlazaId): Collection
    {
        $allocationSubquery = PaymentAllocation::query()
            ->selectRaw('payment_allocations.payment_id, SUM(payment_allocations.amount) as allocated_total')
            ->groupBy('payment_allocations.payment_id');

        $query = Payment::query()
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
            ->orderByDesc('payments.paid_at');

        if ($currentPlazaId !== null) {
            $query->where('properties.plaza_id', $currentPlazaId);
        }

        return $query
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
