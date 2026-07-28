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
use App\Support\ContractOverdueQuery;
use App\Support\DateDisplay;
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
            __('dashboard.flash.checklist_hidden', [
                'date' => DateDisplay::formatDate($dismissedUntil),
            ])
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
            session()->flash('error', __('dashboard.flash.month_closed', ['month' => $currentMonth]));

            return;
        }

        $result = $action->executeForOrganization($currentMonth, $organizationId);

        session()->flash(
            'success',
            __('dashboard.flash.rents_generated', [
                'month' => $currentMonth,
                'created' => $result['created'],
                'skipped' => $result['skipped'],
            ])
        );
    }

    public function render(
        OperatingIncomeService $operatingIncomeService,
        OrganizationSettingsService $settingsService,
        ContractOverdueQuery $overdueQuery
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

        $overdueStatusSql = $overdueQuery->statusSql($todayDate);
        $overdueDaysSql = $overdueQuery->daysSql($todayDate);

        $overduePortfolioTotal = $this->overduePortfolioTotal($overdueQuery, $todayDate, $overdueStatusSql, $currentPlazaId);
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
            $overdueQuery,
            $todayDate,
            $overdueStatusSql,
            $overdueDaysSql,
            'overdue',
            $currentPlazaId
        );
        $graceContracts = $this->contractsByStatus(
            $overdueQuery,
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
            'title' => __('dashboard.title'),
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
                'title' => __('dashboard.onboarding.properties.title'),
                'description' => __('dashboard.onboarding.properties.description'),
                'complete' => $propertiesCount > 0,
                'ctas' => [
                    ['type' => 'route', 'label' => __('dashboard.onboarding.properties.cta_properties'), 'route' => 'properties.index'],
                    ['type' => 'route', 'label' => __('dashboard.onboarding.properties.cta_new_property'), 'route' => 'houses.create'],
                ],
            ],
            [
                'key' => 'units',
                'title' => __('dashboard.onboarding.units.title'),
                'description' => __('dashboard.onboarding.units.description'),
                'complete' => $unitsCount > 0,
                'ctas' => [
                    ['type' => 'route', 'label' => __('dashboard.onboarding.units.cta_manage'), 'route' => 'properties.index'],
                ],
            ],
            [
                'key' => 'tenants',
                'title' => __('dashboard.onboarding.tenants.title'),
                'description' => __('dashboard.onboarding.tenants.description'),
                'complete' => $tenantsCount > 0,
                'ctas' => [
                    ['type' => 'route', 'label' => __('dashboard.onboarding.tenants.cta_tenants'), 'route' => 'tenants.index'],
                ],
            ],
            [
                'key' => 'contracts',
                'title' => __('dashboard.onboarding.contracts.title'),
                'description' => __('dashboard.onboarding.contracts.description'),
                'complete' => $activeContractsCount > 0,
                'ctas' => [
                    ['type' => 'route', 'label' => __('common.new_contract'), 'route' => 'contracts.create'],
                ],
            ],
            [
                'key' => 'rent_charges',
                'title' => __('dashboard.onboarding.rent_charges.title'),
                'description' => __('dashboard.onboarding.rent_charges.description', ['month' => $currentMonth]),
                'complete' => $rentChargesCurrentMonthCount > 0,
                'ctas' => [
                    ['type' => 'action_generate_rent', 'label' => __('dashboard.onboarding.rent_charges.cta_generate')],
                ],
            ],
        ];

        $recommendedSteps = [
            [
                'key' => 'payments',
                'title' => __('dashboard.onboarding.payments.title'),
                'description' => __('dashboard.onboarding.payments.description'),
                'complete' => $paymentsCount > 0,
                'ctas' => [
                    ['type' => 'action_open_quick_payment', 'label' => __('common.register_payment')],
                ],
            ],
            [
                'key' => 'expenses',
                'title' => __('dashboard.onboarding.expenses.title'),
                'description' => __('dashboard.onboarding.expenses.description'),
                'complete' => $expensesCount > 0,
                'ctas' => [
                    ['type' => 'action_open_quick_expense', 'label' => __('common.register_expense')],
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
        ContractOverdueQuery $overdueQuery,
        string $todayDate,
        string $overdueStatusSql,
        string $overdueDaysSql,
        string $status,
        ?int $currentPlazaId
    ): Collection {
        $query = $this->contractsLedgerBaseQuery($overdueQuery, $todayDate, $overdueStatusSql, $overdueDaysSql, $currentPlazaId)
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

    private function overduePortfolioTotal(
        ContractOverdueQuery $overdueQuery,
        string $todayDate,
        string $overdueStatusSql,
        ?int $currentPlazaId
    ): float {
        $total = $this->contractsLedgerBaseQuery(
            $overdueQuery,
            $todayDate,
            $overdueStatusSql,
            $overdueQuery->daysSql($todayDate),
            $currentPlazaId
        )
            ->whereRaw("{$overdueStatusSql} = 'overdue'")
            ->sum(DB::raw('COALESCE(balance_stats.pending_balance, 0)'));

        return round((float) $total, 2);
    }

    private function contractsLedgerBaseQuery(
        ContractOverdueQuery $overdueQuery,
        string $todayDate,
        string $overdueStatusSql,
        string $overdueDaysSql,
        ?int $currentPlazaId
    ): Builder {
        $balanceSubquery = $overdueQuery->balanceByContractSubquery();
        $oldestPendingRentSubquery = $overdueQuery->oldestPendingRentSubquery();

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
}
