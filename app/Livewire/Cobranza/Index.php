<?php

namespace App\Livewire\Cobranza;

use App\Models\Contract;
use App\Models\Property;
use App\Models\Unit;
use App\Support\ContractOverdueQuery;
use App\Support\OrganizationSettingsService;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

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

    public function mount(): void
    {
        if (! (auth()->user()?->can('cobranza.view') ?? false)) {
            abort(403);
        }
    }

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

    public function render(ContractOverdueQuery $overdueQuery): View
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

        $contracts = $this->buildQuery($overdueQuery)->paginate(12);
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
            $dueDate = $row->due_date
                ? \Carbon\Carbon::parse((string) $row->due_date)->format('Y-m-d')
                : __('cobranza.whatsapp.no_due_date');
            $graceUntil = $row->grace_until
                ? \Carbon\Carbon::parse((string) $row->grace_until)->format('Y-m-d')
                : __('cobranza.whatsapp.no_grace');
            $message = $settingsService->renderTemplate(
                (string) $settings['whatsapp_template'],
                [
                    'tenant_name' => (string) $row->tenant_name,
                    'unit_name' => $unitLabel,
                    'amount_due' => number_format($pendingBalance, 2, '.', ''),
                    'shared_receipt_url' => (string) ($shareableLink ?: ''),
                ]
            );

            $message .= ' '.__('cobranza.whatsapp.due_line', ['date' => $dueDate])
                .' '.__('cobranza.whatsapp.grace_line', ['date' => $graceUntil]);

            $row->shareable_link = $shareableLink;
            $row->whatsapp_message = $message;

            return $row;
        });

        return view('livewire.cobranza.index', [
            'contracts' => $contracts,
            'properties' => $properties,
            'units' => $units,
            'canCreatePayments' => auth()->user()?->can('payments.create') ?? false,
        ])->layout('layouts.app', [
            'title' => __('cobranza.title'),
        ]);
    }

    private function buildQuery(ContractOverdueQuery $overdueQuery): Builder
    {
        $today = now('America/Tijuana')->toDateString();
        $balanceSubquery = $overdueQuery->balanceByContractSubquery();
        $oldestPendingRentSubquery = $overdueQuery->oldestPendingRentSubquery(includePeriod: true);
        $latestPaymentSubquery = $overdueQuery->latestPaymentByContractSubquery();

        $overdueStatusSql = $overdueQuery->statusSql($today);
        $overdueDaysSql = $overdueQuery->daysSql($today);

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
}
