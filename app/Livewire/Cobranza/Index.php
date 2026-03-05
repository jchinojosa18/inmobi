<?php

namespace App\Livewire\Cobranza;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\Unit;
use App\Support\OrganizationSettingsService;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    private ?string $databaseDriver = null;

    #[On('payment-registered')]
    public function onPaymentRegistered(): void {}

    public string $tab = 'overdue';

    public string $property_id = '';

    public string $unit_id = '';

    public string $q = '';

    public string $days_min = '';

    public string $days_max = '';

    /**
     * @var array<string, array<string, string>>
     */
    protected $queryString = [
        'tab' => ['except' => 'overdue'],
        'property_id' => ['except' => ''],
        'unit_id' => ['except' => ''],
        'q' => ['except' => ''],
        'days_min' => ['except' => ''],
        'days_max' => ['except' => ''],
    ];

    public function updatingTab(): void
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

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function updatingDaysMin(): void
    {
        $this->resetPage();
    }

    public function updatingDaysMax(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        if (! in_array($this->tab, ['overdue', 'grace', 'current'], true)) {
            $this->tab = 'overdue';
        }

        $propertiesQuery = Property::query()
            ->orderBy('name')
            ->select(['id', 'name']);

        TenantContext::applyCurrentPlazaFilter($propertiesQuery, 'properties.plaza_id');

        $properties = $propertiesQuery->get();

        $units = collect();
        if ($this->property_id !== '') {
            $unitsQuery = Unit::query()
                ->join('properties', 'properties.id', '=', 'units.property_id')
                ->where('units.property_id', (int) $this->property_id)
                ->orderBy('units.name')
                ->select(['units.id', 'units.name', 'units.code']);

            TenantContext::applyCurrentPlazaFilter($unitsQuery, 'properties.plaza_id');

            $units = $unitsQuery->get();
        }

        $contracts = $this->buildQuery()->paginate(12);
        $settingsService = app(OrganizationSettingsService::class);
        $settings = $settingsService->current();

        $contracts->getCollection()->transform(function ($row) use ($settingsService, $settings) {
            $shareableLink = null;

            if (! empty($row->latest_payment_id)) {
                $shareableLink = URL::temporarySignedRoute(
                    'payments.receipt.share',
                    now()->addDays(7),
                    ['paymentId' => (int) $row->latest_payment_id]
                );
            }

            $unitLabel = trim((string) ($row->property_name.' / '.($row->unit_name ?: ($row->unit_code ?: 'N/D'))));
            $pendingBalance = round((float) ($row->pending_balance ?? 0), 2);
            $dueDate = $row->due_date ? \Carbon\Carbon::parse((string) $row->due_date)->format('Y-m-d') : 'Sin vencimiento';
            $graceUntil = $row->grace_until ? \Carbon\Carbon::parse((string) $row->grace_until)->format('Y-m-d') : 'Sin gracia';
            $message = $settingsService->renderTemplate(
                (string) $settings['whatsapp_template'],
                [
                    'tenant_name' => (string) $row->tenant_name,
                    'unit_name' => $unitLabel,
                    'amount_due' => number_format($pendingBalance, 2, '.', ''),
                    'shared_receipt_url' => (string) ($shareableLink ?: ''),
                ]
            );

            $message .= " Vence: {$dueDate}. Gracia: {$graceUntil}.";

            $row->shareable_link = $shareableLink;
            $row->whatsapp_message = $message;

            return $row;
        });

        return view('livewire.cobranza.index', [
            'contracts' => $contracts,
            'properties' => $properties,
            'units' => $units,
        ])->layout('layouts.app', [
            'title' => 'Cobranza',
        ]);
    }

    private function buildQuery(): Builder
    {
        $today = now('America/Tijuana')->toDateString();
        $balanceSubquery = $this->balanceByContractSubquery();
        $oldestPendingRentSubquery = $this->oldestPendingRentSubquery();
        $latestPaymentSubquery = $this->latestPaymentByContractSubquery();

        $overdueStatusSql = $this->overdueStatusSql($today);
        $overdueDaysSql = $this->overdueDaysSql($today);

        $query = Contract::query()
            ->select([
                'contracts.id as contract_id',
                'tenants.full_name as tenant_name',
                'tenants.email as tenant_email',
                'tenants.phone as tenant_phone',
                'properties.name as property_name',
                'units.name as unit_name',
                'units.code as unit_code',
                DB::raw('COALESCE(balance_stats.pending_balance, 0) as pending_balance'),
                DB::raw('COALESCE(credit_balances.balance, 0) as credit_balance'),
                DB::raw('rent_status.period as overdue_period'),
                DB::raw('rent_status.due_date as due_date'),
                DB::raw('rent_status.grace_until as grace_until'),
                DB::raw('latest_payment.payment_id as latest_payment_id'),
                DB::raw("{$overdueStatusSql} as overdue_status"),
                DB::raw("{$overdueDaysSql} as overdue_days"),
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
            ->leftJoinSub($latestPaymentSubquery, 'latest_payment', function ($join): void {
                $join->on('latest_payment.contract_id', '=', 'contracts.id');
            })
            ->leftJoin('credit_balances', function ($join): void {
                $join->on('credit_balances.contract_id', '=', 'contracts.id')
                    ->whereNull('credit_balances.deleted_at');
            })
            ->whereColumn('units.organization_id', 'contracts.organization_id')
            ->whereColumn('properties.organization_id', 'contracts.organization_id')
            ->whereColumn('tenants.organization_id', 'contracts.organization_id');

        TenantContext::applyCurrentPlazaFilter($query, 'properties.plaza_id');

        $this->applyFilters($query, $overdueStatusSql, $overdueDaysSql);
        $this->applySorting($query, $overdueDaysSql);

        return $query;
    }

    private function applyFilters(Builder $query, string $overdueStatusSql, string $overdueDaysSql): void
    {
        $query->whereRaw("{$overdueStatusSql} = ?", [$this->tab]);

        if ($this->q !== '') {
            $term = '%'.trim($this->q).'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder
                    ->where('tenants.full_name', 'like', $term)
                    ->orWhere('tenants.email', 'like', $term)
                    ->orWhere('tenants.phone', 'like', $term);
            });
        }

        if ($this->property_id !== '') {
            $query->where('properties.id', (int) $this->property_id);
        }

        if ($this->unit_id !== '') {
            $query->where('units.id', (int) $this->unit_id);
        }

        if ($this->days_min !== '' && is_numeric($this->days_min)) {
            $query->whereRaw("{$overdueDaysSql} >= ?", [(int) $this->days_min]);
        }

        if ($this->days_max !== '' && is_numeric($this->days_max)) {
            $query->whereRaw("{$overdueDaysSql} <= ?", [(int) $this->days_max]);
        }
    }

    private function applySorting(Builder $query, string $overdueDaysSql): void
    {
        if ($this->tab === 'overdue') {
            $query->orderByRaw("{$overdueDaysSql} desc")
                ->orderByRaw('COALESCE(balance_stats.pending_balance, 0) desc');

            return;
        }

        if ($this->tab === 'grace') {
            $query->orderByRaw("COALESCE(rent_status.grace_until, '9999-12-31') asc")
                ->orderByRaw('COALESCE(balance_stats.pending_balance, 0) desc');

            return;
        }

        $query->orderBy('tenants.full_name')
            ->orderBy('contracts.id');
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

    private function oldestPendingRentSubquery(): QueryBuilder
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
                charges.period as period,
                {$dueDateExpression} as due_date,
                {$graceUntilExpression} as grace_until,
                ROW_NUMBER() OVER (
                    PARTITION BY charges.contract_id
                    ORDER BY {$dueDateExpression} asc, charges.id asc
                ) as row_num
            ");

        return DB::query()
            ->fromSub($rankedSubquery, 'rent_rows')
            ->selectRaw('rent_rows.contract_id, rent_rows.period, rent_rows.due_date, rent_rows.grace_until')
            ->where('rent_rows.row_num', 1);
    }

    private function latestPaymentByContractSubquery(): QueryBuilder
    {
        $rankedPayments = Payment::query()
            ->selectRaw('
                payments.contract_id,
                payments.id as payment_id,
                ROW_NUMBER() OVER (
                    PARTITION BY payments.contract_id
                    ORDER BY payments.paid_at desc, payments.id desc
                ) as row_num
            ');

        return DB::query()
            ->fromSub($rankedPayments, 'payment_rows')
            ->selectRaw('payment_rows.contract_id, payment_rows.payment_id')
            ->where('payment_rows.row_num', 1);
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
        $diffExpression = $this->overdueDiffExpression($todayDate);

        return "CASE
            WHEN rent_status.contract_id IS NULL THEN 0
            WHEN {$todayLiteral} > rent_status.grace_until THEN {$diffExpression}
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

    private function databaseDriver(): string
    {
        if ($this->databaseDriver === null) {
            $this->databaseDriver = DB::connection()->getDriverName();
        }

        return $this->databaseDriver;
    }
}
