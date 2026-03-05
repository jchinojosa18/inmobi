<?php

namespace App\Livewire\Properties;

use App\Models\Organization;
use App\Models\Plaza;
use App\Models\Property;
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

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function startEdit(int $propertyId): void
    {
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

        if ($this->editingId !== null) {
            $property = Property::query()->findOrFail($this->editingId);
            $property->update($payload);
            session()->flash('success', 'Propiedad actualizada correctamente.');
        } else {
            Property::query()->create([
                ...$payload,
                'kind' => Property::KIND_BUILDING,
            ]);
            session()->flash('success', 'Propiedad creada correctamente.');
        }

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
        ])->layout('layouts.app', [
            'title' => 'Propiedades',
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
            'name.required' => 'El nombre de la propiedad es obligatorio.',
            'name.max' => 'El nombre no debe exceder 150 caracteres.',
            'code.unique' => 'El código ya existe en esta organización.',
            'formStatus.required' => 'Selecciona un estado.',
            'formStatus.in' => 'El estado seleccionado no es válido.',
            'address.max' => 'La dirección no debe exceder 255 caracteres.',
            'notes.max' => 'Las notas no deben exceder 1000 caracteres.',
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
