<?php

namespace App\Livewire\Units;

use App\Models\Contract;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public Property $property;

    public string $search = '';

    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public ?string $code = null;

    public string $formStatus = 'active';

    public ?string $floor = null;

    public ?string $notes = null;

    /**
     * @var array<string, array<string, string>>
     */
    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
    ];

    public function mount(Property $property): void
    {
        if (! (auth()->user()?->can('units.view') ?? false)) {
            abort(403);
        }

        $this->property = $property;

        if ($this->property->isStandaloneHouse()) {
            $this->redirectRoute('houses.show', ['property' => $this->property->id], navigate: false);
        }
    }

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
        if (! (auth()->user()?->can('units.manage') ?? false)) {
            abort(403);
        }

        $this->resetForm();
        $this->showForm = true;
    }

    public function startEdit(int $unitId): void
    {
        if (! (auth()->user()?->can('units.manage') ?? false)) {
            abort(403);
        }

        $unit = Unit::query()
            ->where('property_id', $this->property->id)
            ->findOrFail($unitId);

        $this->editingId = $unit->id;
        $this->name = $unit->name;
        $this->code = $unit->code;
        $this->formStatus = $unit->status;
        $this->floor = $unit->floor;
        $this->notes = $unit->notes;
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        if (! (auth()->user()?->can('units.manage') ?? false)) {
            abort(403);
        }

        $validated = $this->validate($this->rules(), $this->messages());

        $payload = [
            'organization_id' => auth()->user()?->organization_id,
            'property_id' => $this->property->id,
            'name' => $validated['name'],
            'code' => $validated['code'] ?: null,
            'status' => $validated['formStatus'],
            'floor' => $validated['floor'] ?: null,
            'notes' => $validated['notes'] ?: null,
        ];

        if ($this->editingId !== null) {
            $unit = Unit::query()
                ->where('property_id', $this->property->id)
                ->findOrFail($this->editingId);

            $unit->update($payload);
            session()->flash('success', 'Unidad actualizada correctamente.');
        } else {
            if ($this->property->isStandaloneHouse()) {
                $this->addError('name', 'Las casas standalone ya contienen una unidad única.');

                return;
            }

            Unit::query()->create([
                ...$payload,
                'kind' => Unit::KIND_APARTMENT,
            ]);
            session()->flash('success', 'Unidad creada correctamente.');
        }

        $this->resetForm();
        $this->resetPage();
    }

    public function render(): View
    {
        $units = Unit::query()
            ->where('property_id', $this->property->id)
            ->withCount([
                'contracts as active_contracts_count' => fn ($query) => $query->where('status', Contract::STATUS_ACTIVE),
            ])
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($innerQuery): void {
                    $innerQuery
                        ->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('code', 'like', '%'.$this->search.'%')
                        ->orWhere('floor', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.units.index', [
            'units' => $units,
            'canManageUnits' => auth()->user()?->can('units.manage') ?? false,
        ])->layout('layouts.app', [
            'title' => 'Unidades',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('units', 'code')
                    ->where(fn ($query) => $query
                        ->where('property_id', $this->property->id)
                        ->whereNull('deleted_at')
                    )
                    ->ignore($this->editingId),
            ],
            'formStatus' => ['required', Rule::in(['active', 'inactive'])],
            'floor' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'name.required' => 'El nombre de la unidad es obligatorio.',
            'name.max' => 'El nombre no debe exceder 120 caracteres.',
            'code.unique' => 'El código ya existe en esta propiedad.',
            'formStatus.required' => 'Selecciona un estado.',
            'formStatus.in' => 'El estado seleccionado no es válido.',
            'floor.max' => 'El nivel/piso no debe exceder 50 caracteres.',
            'notes.max' => 'Las notas no deben exceder 1000 caracteres.',
        ];
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId',
            'name',
            'code',
            'floor',
            'notes',
        ]);

        $this->formStatus = 'active';
        $this->showForm = false;
        $this->resetValidation();
    }
}
