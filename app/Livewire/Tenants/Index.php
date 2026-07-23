<?php

namespace App\Livewire\Tenants;

use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $full_name = '';

    public ?string $email = null;

    public ?string $phone = null;

    public ?string $ine_clave = null;

    public string $formStatus = 'active';

    public ?string $notes = null;

    /**
     * @var array<string, array<string, string>>
     */
    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        if (! (auth()->user()?->can('tenants.view') ?? false)) {
            abort(403);
        }
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function startCreate(): void
    {
        if (! (auth()->user()?->can('tenants.manage') ?? false)) {
            abort(403);
        }

        $this->resetForm();
        $this->showForm = true;
    }

    public function startEdit(int $tenantId): void
    {
        if (! (auth()->user()?->can('tenants.manage') ?? false)) {
            abort(403);
        }

        $tenant = Tenant::query()->findOrFail($tenantId);

        $this->editingId = $tenant->id;
        $this->full_name = $tenant->full_name;
        $this->email = $tenant->email;
        $this->phone = $tenant->phone;
        $this->ine_clave = $tenant->ine_clave;
        $this->formStatus = $tenant->status;
        $this->notes = $tenant->notes;
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        if (! (auth()->user()?->can('tenants.manage') ?? false)) {
            abort(403);
        }

        $this->ine_clave = $this->normalizeIneClave($this->ine_clave);

        $validated = $this->validate($this->rules(), $this->messages());

        $payload = [
            'organization_id' => auth()->user()?->organization_id,
            'full_name' => $validated['full_name'],
            'email' => $validated['email'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'ine_clave' => $validated['ine_clave'] ?? null,
            'status' => $validated['formStatus'],
            'notes' => $validated['notes'] ?: null,
        ];

        if ($this->editingId !== null) {
            $tenant = Tenant::query()->findOrFail($this->editingId);
            $tenant->update($payload);
            session()->flash('success', __('catalog.flash.tenant_updated'));
        } else {
            Tenant::query()->create($payload);
            session()->flash('success', __('catalog.flash.tenant_created'));
        }

        $this->resetForm();
        $this->resetPage();
    }

    public function render(): View
    {
        $tenants = Tenant::query()
            ->withCount('contracts')
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($innerQuery): void {
                    $innerQuery
                        ->where('full_name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%')
                        ->orWhere('phone', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderBy('full_name')
            ->paginate(10);

        return view('livewire.tenants.index', [
            'tenants' => $tenants,
            'canManageTenants' => auth()->user()?->can('tenants.manage') ?? false,
        ])->layout('layouts.app', [
            'title' => __('catalog.tenants.title'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:50'],
            'ine_clave' => [
                'nullable',
                'string',
                'size:18',
                'regex:/^[A-Z0-9]{18}$/',
                Rule::unique('tenants', 'ine_clave')
                    ->where(fn ($query) => $query->where('organization_id', auth()->user()?->organization_id))
                    ->ignore($this->editingId),
            ],
            'formStatus' => ['required', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'full_name.required' => __('catalog.validation.full_name_required'),
            'full_name.max' => __('catalog.validation.full_name_max'),
            'email.email' => __('catalog.validation.email_invalid'),
            'email.max' => __('catalog.validation.email_max'),
            'phone.max' => __('catalog.validation.phone_max'),
            'ine_clave.size' => __('catalog.validation.ine_clave_format'),
            'ine_clave.regex' => __('catalog.validation.ine_clave_format'),
            'ine_clave.unique' => __('catalog.validation.ine_clave_unique'),
            'formStatus.required' => __('catalog.validation.status_required'),
            'formStatus.in' => __('catalog.validation.status_invalid'),
            'notes.max' => __('catalog.validation.notes_max'),
        ];
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId',
            'full_name',
            'email',
            'phone',
            'ine_clave',
            'notes',
        ]);

        $this->formStatus = 'active';
        $this->showForm = false;
        $this->resetValidation();
    }

    private function normalizeIneClave(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper(trim($value));

        return $normalized === '' ? null : $normalized;
    }
}
