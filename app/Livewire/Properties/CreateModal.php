<?php

namespace App\Livewire\Properties;

use App\Livewire\Concerns\NormalizesPropertyUppercaseFields;
use App\Models\Organization;
use App\Models\Plaza;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class CreateModal extends Component
{
    use NormalizesPropertyUppercaseFields;

    public const TYPE_HOUSE = 'house';

    public const TYPE_BUILDING = 'building';

    public const TYPE_LOCAL = 'local';

    public const TYPE_LAND = 'land';

    public bool $open = false;

    public string $step = 'picker';

    public ?string $selectedType = null;

    public string $name = '';

    public ?string $code = null;

    public string $formStatus = 'active';

    public ?string $address = null;

    public ?string $notes = null;

    public ?int $plazaId = null;

    #[On('open-property-create')]
    public function open(): void
    {
        if (! (auth()->user()?->can('properties.manage') ?? false)) {
            abort(403);
        }

        $this->resetForm();
        $this->open = true;
    }

    public function selectType(string $type): void
    {
        if (! in_array($type, [
            self::TYPE_HOUSE,
            self::TYPE_BUILDING,
            self::TYPE_LOCAL,
            self::TYPE_LAND,
        ], true)) {
            return;
        }

        $this->selectedType = $type;
        $this->step = 'form';
        $this->resetValidation();
    }

    public function backToPicker(): void
    {
        $this->step = 'picker';
        $this->selectedType = null;
        $this->reset([
            'name',
            'code',
            'address',
            'notes',
            'plazaId',
        ]);
        $this->formStatus = 'active';
        $this->resetValidation();
    }

    public function cancelForm(): void
    {
        $this->close();
    }

    public function close(): void
    {
        $this->open = false;
        $this->resetForm();
    }

    public function save(): void
    {
        if (! (auth()->user()?->can('properties.manage') ?? false)) {
            abort(403);
        }

        if ($this->selectedType === self::TYPE_BUILDING) {
            $this->saveBuilding();

            return;
        }

        if (in_array($this->selectedType, [self::TYPE_HOUSE, self::TYPE_LOCAL, self::TYPE_LAND], true)) {
            $this->saveStandalone();

            return;
        }
    }

    public function render(): View
    {
        return view('livewire.properties.create-modal', [
            'typeOptions' => $this->typeOptions(),
            'selectedTypeLabel' => $this->selectedTypeLabel(),
        ]);
    }

    /**
     * @return list<array{key: string, label: string, description: string}>
     */
    private function typeOptions(): array
    {
        return [
            [
                'key' => self::TYPE_HOUSE,
                'label' => 'Casa',
                'description' => 'Vivienda independiente con una sola unidad.',
            ],
            [
                'key' => self::TYPE_BUILDING,
                'label' => 'Edificio',
                'description' => 'Departamentos por piso; genera unidades después.',
            ],
            [
                'key' => self::TYPE_LOCAL,
                'label' => 'Local',
                'description' => 'Espacio comercial único.',
            ],
            [
                'key' => self::TYPE_LAND,
                'label' => 'Terreno',
                'description' => 'Lote único sin subdivisión inicial.',
            ],
        ];
    }

    private function selectedTypeLabel(): string
    {
        return match ($this->selectedType) {
            self::TYPE_HOUSE => 'Casa',
            self::TYPE_BUILDING => 'Edificio',
            self::TYPE_LOCAL => 'Local',
            self::TYPE_LAND => 'Terreno',
            default => 'Inmueble',
        };
    }

    private function saveStandalone(): void
    {
        $this->normalizePropertyUppercaseFields();
        $validated = $this->validate($this->standaloneRules(), $this->standaloneMessages());
        $config = $this->standaloneConfig((string) $this->selectedType);

        DB::transaction(function () use ($validated, $config): void {
            $organizationId = (int) auth()->user()?->organization_id;
            $plazaId = $this->resolvePlazaIdForSave($validated['plazaId'] ?? null);

            $property = Property::query()->create([
                'organization_id' => $organizationId,
                'plaza_id' => $plazaId,
                'name' => $validated['name'],
                'code' => null,
                'status' => 'active',
                'kind' => $config['property_kind'],
                'address' => $validated['address'] ?: null,
                'notes' => $validated['notes'] ?: null,
            ]);

            Unit::query()->create([
                'organization_id' => $organizationId,
                'property_id' => $property->id,
                'name' => $config['unit_name'],
                'code' => null,
                'status' => 'active',
                'kind' => $config['unit_kind'],
                'floor' => null,
                'notes' => null,
            ]);
        });

        session()->flash('success', $this->selectedTypeLabel().' creado correctamente.');
        $this->close();
        $this->dispatch('property-created');
    }

    private function saveBuilding(): void
    {
        $this->normalizePropertyUppercaseFields();
        $validated = $this->validate($this->buildingRules(), $this->buildingMessages());

        $property = Property::query()->create([
            'organization_id' => auth()->user()?->organization_id,
            'plaza_id' => $this->resolvePlazaIdForSave($validated['plazaId'] ?? null),
            'name' => $validated['name'],
            'code' => $validated['code'],
            'status' => $validated['formStatus'],
            'kind' => Property::KIND_BUILDING,
            'address' => $validated['address'] ?: null,
            'notes' => $validated['notes'] ?: null,
        ]);

        session()->flash('success', 'Edificio creado correctamente. Genera las unidades a continuación.');
        $this->close();

        $this->redirectRoute('properties.units.index', [
            'property' => $property->id,
            'bulk' => 1,
        ], navigate: false);
    }

    /**
     * @return array{property_kind: string, unit_kind: string, unit_name: string}
     */
    private function standaloneConfig(string $type): array
    {
        return match ($type) {
            self::TYPE_LOCAL => [
                'property_kind' => Property::KIND_LOCAL,
                'unit_kind' => Unit::KIND_LOCAL,
                'unit_name' => 'Local',
            ],
            self::TYPE_LAND => [
                'property_kind' => Property::KIND_LAND,
                'unit_kind' => Unit::KIND_LAND,
                'unit_name' => 'Terreno',
            ],
            default => [
                'property_kind' => Property::KIND_STANDALONE_HOUSE,
                'unit_kind' => Unit::KIND_HOUSE,
                'unit_name' => 'Casa',
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function standaloneRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
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
     * @return array<string, mixed>
     */
    private function buildingRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('properties', 'code')
                    ->where(fn ($query) => $query
                        ->where('organization_id', auth()->user()?->organization_id)
                        ->whereNull('deleted_at')
                    ),
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
    private function standaloneMessages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre no debe exceder 150 caracteres.',
            'address.max' => 'La dirección no debe exceder 255 caracteres.',
            'notes.max' => 'Las notas no deben exceder 1000 caracteres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildingMessages(): array
    {
        return [
            'name.required' => 'El nombre del edificio es obligatorio.',
            'name.max' => 'El nombre no debe exceder 150 caracteres.',
            'code.required' => 'El código es obligatorio para generar unidades.',
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
            'step',
            'selectedType',
            'name',
            'code',
            'address',
            'notes',
            'plazaId',
        ]);

        $this->step = 'picker';
        $this->formStatus = 'active';
        $this->resetValidation();
    }
}
