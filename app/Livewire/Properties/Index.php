<?php

namespace App\Livewire\Properties;

use App\Livewire\Concerns\NormalizesPropertyUppercaseFields;
use App\Models\Organization;
use App\Models\Plaza;
use App\Models\Property;
use App\Support\UnitNumberingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use NormalizesPropertyUppercaseFields;
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public ?string $code = null;

    public string $formStatus = 'active';

    public ?string $address = null;

    public ?string $notes = null;

    public ?int $plazaId = null;

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
        if (! (auth()->user()?->can('properties.view') ?? false)) {
            abort(403);
        }

        if ((request()->boolean('create_house') || request()->boolean('create')) && (auth()->user()?->can('properties.manage') ?? false)) {
            $this->dispatch('open-property-create');
        }
    }

    #[On('property-created')]
    public function onPropertyCreated(): void
    {
        $this->resetPage();
    }

    #[On('house-created')]
    public function onHouseCreated(): void
    {
        $this->onPropertyCreated();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function startEdit(int $propertyId): void
    {
        if (! (auth()->user()?->can('properties.manage') ?? false)) {
            abort(403);
        }

        $property = Property::query()->findOrFail($propertyId);

        $this->editingId = $property->id;
        $this->name = $property->name;
        $this->code = $property->code;
        $this->formStatus = $property->status;
        $this->address = $property->address;
        $this->notes = $property->notes;
        $this->plazaId = $property->plaza_id;
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        if (! (auth()->user()?->can('properties.manage') ?? false)) {
            abort(403);
        }

        $this->normalizePropertyUppercaseFields();
        $validated = $this->validate($this->rules(), $this->messages());

        $payload = [
            'organization_id' => auth()->user()?->organization_id,
            'plaza_id' => $this->resolvePlazaIdForSave($validated['plazaId'] ?? null),
            'name' => $validated['name'],
            'code' => $validated['code'] ?: null,
            'status' => $validated['formStatus'],
            'address' => $validated['address'] ?: null,
            'notes' => $validated['notes'] ?: null,
        ];

        if ($this->editingId === null) {
            return;
        }

        $property = Property::query()->findOrFail($this->editingId);
        $oldCode = $property->code !== null ? trim((string) $property->code) : '';
        $newCode = $payload['code'] !== null ? trim((string) $payload['code']) : '';
        $numberingService = app(UnitNumberingService::class);

        if ($newCode === '' && $oldCode !== '' && $numberingService->propertyHasPrefixedUnits($property, $oldCode)) {
            throw ValidationException::withMessages([
                'code' => __('catalog.validation.property_code_required_with_units'),
            ]);
        }

        DB::transaction(function () use ($property, $payload, $oldCode, $newCode, $numberingService): void {
            $property->update($payload);

            if ($oldCode !== '' && $newCode !== '' && $oldCode !== $newCode) {
                $numberingService->syncUnitCodesAfterPropertyCodeChange(
                    $property->fresh(),
                    $oldCode,
                    $newCode,
                );
            }
        });

        session()->flash('success', __('catalog.flash.property_updated'));

        $this->resetForm();
        $this->resetPage();
    }

    public function render(): View
    {
        $properties = Property::query()
            ->withCount('units')
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($innerQuery): void {
                    $innerQuery
                        ->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('code', 'like', '%'.$this->search.'%')
                        ->orWhere('address', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.properties.index', [
            'properties' => $properties,
            'canManageProperties' => auth()->user()?->can('properties.manage') ?? false,
        ])->layout('layouts.app', [
            'title' => __('catalog.properties.title'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('properties', 'code')
                    ->where(fn ($query) => $query
                        ->where('organization_id', auth()->user()?->organization_id)
                        ->whereNull('deleted_at')
                    )
                    ->ignore($this->editingId),
            ],
            'formStatus' => ['required', Rule::in(['active', 'inactive'])],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'plazaId' => [
                'nullable',
                'integer',
                Rule::exists('plazas', 'id')->where(
                    fn ($query) => $query->where('organization_id', auth()->user()?->organization_id)
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'name.required' => __('catalog.validation.name_required'),
            'name.max' => __('catalog.validation.name_max'),
            'code.unique' => __('catalog.validation.code_unique'),
            'formStatus.required' => __('catalog.validation.status_required'),
            'formStatus.in' => __('catalog.validation.status_invalid'),
            'address.max' => __('catalog.validation.address_max'),
            'notes.max' => __('catalog.validation.notes_max'),
        ];
    }

    private function resolvePlazaIdForSave(?int $requestedPlazaId): int
    {
        $organizationId = (int) (auth()->user()?->organization_id ?? 0);
        $organization = Organization::query()->findOrFail($organizationId);

        if ($requestedPlazaId !== null) {
            $exists = Plaza::query()
                ->where('id', $requestedPlazaId)
                ->where('organization_id', $organizationId)
                ->exists();

            if ($exists) {
                return $requestedPlazaId;
            }
        }

        return (int) $organization->ensureDefaultPlaza(
            auth()->id() !== null ? (int) auth()->id() : null
        )->id;
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId',
            'name',
            'code',
            'address',
            'notes',
            'plazaId',
        ]);

        $this->formStatus = 'active';
        $this->showForm = false;
        $this->resetValidation();
    }
}
