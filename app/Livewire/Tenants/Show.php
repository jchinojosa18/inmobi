<?php

namespace App\Livewire\Tenants;

use App\Models\Tenant;
use App\Support\TenantKardexSummary;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Show extends Component
{
    public const DEFAULT_TAB = 'contracts';

    /**
     * @var list<string>
     */
    public const TABS = ['contracts', 'charges', 'payments'];

    public Tenant $tenant;

    public string $tab = self::DEFAULT_TAB;

    public bool $showForm = false;

    public string $full_name = '';

    public ?string $email = null;

    public ?string $phone = null;

    public string $formStatus = 'active';

    public ?string $notes = null;

    /**
     * @var array<string, array<string, string>>
     */
    protected $queryString = [
        'tab' => ['except' => self::DEFAULT_TAB],
    ];

    public function mount(Tenant $tenant): void
    {
        if (! (auth()->user()?->can('tenants.view') ?? false)) {
            abort(403);
        }

        $this->tenant = $tenant;
        $this->tab = $this->normalizeTab($this->tab);
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, self::TABS, true)) {
            return;
        }

        $this->tab = $tab;
    }

    public function startEdit(): void
    {
        if (! (auth()->user()?->can('tenants.manage') ?? false)) {
            abort(403);
        }

        $this->full_name = $this->tenant->full_name;
        $this->email = $this->tenant->email;
        $this->phone = $this->tenant->phone;
        $this->formStatus = $this->tenant->status;
        $this->notes = $this->tenant->notes;
        $this->showForm = true;
        $this->resetValidation();
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        if (! (auth()->user()?->can('tenants.manage') ?? false)) {
            abort(403);
        }

        $validated = $this->validate([
            'full_name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:50'],
            'formStatus' => ['required', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'full_name.required' => __('catalog.validation.full_name_required'),
            'full_name.max' => __('catalog.validation.full_name_max'),
            'email.email' => __('catalog.validation.email_invalid'),
            'email.max' => __('catalog.validation.email_max'),
            'phone.max' => __('catalog.validation.phone_max'),
            'formStatus.required' => __('catalog.validation.status_required'),
            'formStatus.in' => __('catalog.validation.status_invalid'),
            'notes.max' => __('catalog.validation.notes_max'),
        ]);

        $this->tenant->update([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'status' => $validated['formStatus'],
            'notes' => $validated['notes'] ?: null,
        ]);

        $this->tenant->refresh();
        $this->showForm = false;
        session()->flash('success', __('catalog.tenants.kardex.flash_updated'));
    }

    public function render(): View
    {
        $tenant = $this->tenant->fresh();
        $summary = TenantKardexSummary::for($tenant, $this->kardexReturnUrl($tenant));

        return view('livewire.tenants.show', [
            'summary' => $summary,
            'contracts' => $summary->contracts(),
            'charges' => $summary->outstandingCharges(),
            'payments' => $summary->recentPayments(),
            'canManageTenants' => auth()->user()?->can('tenants.manage') ?? false,
            'canViewContracts' => auth()->user()?->can('contracts.view') ?? false,
            'canViewPayments' => auth()->user()?->can('payments.view') ?? false,
        ])->layout('layouts.app', [
            'title' => __('catalog.tenants.kardex.page_title'),
        ]);
    }

    private function kardexReturnUrl(Tenant $tenant): string
    {
        $url = route('tenants.show', $tenant, false);
        $tab = $this->normalizeTab($this->tab);

        if ($tab !== self::DEFAULT_TAB) {
            $url .= '?tab='.$tab;
        }

        return $url;
    }

    private function normalizeTab(string $tab): string
    {
        return in_array($tab, self::TABS, true) ? $tab : self::DEFAULT_TAB;
    }
}
