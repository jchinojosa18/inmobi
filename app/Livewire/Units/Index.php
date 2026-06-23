<?php

namespace App\Livewire\Units;

use App\Models\Property;
use App\Models\Unit;
use App\Support\TextCase;
use App\Support\UnitNumberingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public const BULK_NUMBERING_FLOOR_BASED = 'floor_based';

    public const BULK_NUMBERING_SEQUENTIAL = 'sequential';

    public Property $property;

    public string $search = '';

    public string $statusFilter = '';

    public bool $showBulkForm = false;

    public string $bulkNumberingScheme = self::BULK_NUMBERING_FLOOR_BASED;

    /**
     * @var list<string>
     */
    public array $selectedUnitIds = [];

    public bool $showDeleteConfirm = false;

    public string $deleteConfirmType = '';

    public ?int $pendingDeleteUnitId = null;

    public ?string $pendingDeleteUnitName = null;

    public bool $editingBuildingNumberingScheme = false;

    /**
     * @var list<array{floor: string, units: string}>
     */
    public array $floorRows = [
        ['floor' => '1', 'units' => '1'],
    ];

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

        if ($this->property->isStandaloneEntity()) {
            $this->redirectRoute('houses.show', ['property' => $this->property->id], navigate: false);
        }

        if (request()->boolean('bulk') && (auth()->user()?->can('units.manage') ?? false)) {
            $this->resetBulkForm();
            $this->showBulkForm = true;
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->selectedUnitIds = [];
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
        $this->selectedUnitIds = [];
    }

    public function startBulkGenerate(): void
    {
        if (! (auth()->user()?->can('units.manage') ?? false)) {
            abort(403);
        }

        $this->resetBulkForm();
        $this->showBulkForm = true;
    }

    public function cancelBulkForm(): void
    {
        $this->showBulkForm = false;
        $this->editingBuildingNumberingScheme = false;
        $this->resetBulkForm();
    }

    public function startEditingBuildingNumberingScheme(): void
    {
        if (! (auth()->user()?->can('units.manage') ?? false)) {
            abort(403);
        }

        $lockedScheme = app(UnitNumberingService::class)->resolveScheme($this->property);
        $this->bulkNumberingScheme = $lockedScheme === self::BULK_NUMBERING_SEQUENTIAL
            ? self::BULK_NUMBERING_FLOOR_BASED
            : self::BULK_NUMBERING_SEQUENTIAL;
        $this->editingBuildingNumberingScheme = true;
    }

    public function cancelEditingBuildingNumberingScheme(): void
    {
        $this->editingBuildingNumberingScheme = false;
        $this->syncBulkNumberingSchemeFromProperty();
    }

    public function applyBuildingNumberingScheme(UnitNumberingService $numberingService): void
    {
        if (! (auth()->user()?->can('units.manage') ?? false)) {
            abort(403);
        }

        $lockedScheme = $numberingService->resolveScheme($this->property);
        if ($lockedScheme === $this->bulkNumberingScheme) {
            $this->addError('bulkNumberingScheme', 'Selecciona una nomenclatura distinta a la actual del edificio.');

            return;
        }

        if (! in_array($this->bulkNumberingScheme, UnitNumberingService::schemes(), true)) {
            $this->addError('bulkNumberingScheme', 'La nomenclatura seleccionada no es válida.');

            return;
        }

        try {
            $updatedCount = $numberingService->convertPropertyUnits($this->property, $this->bulkNumberingScheme);
        } catch (\InvalidArgumentException $exception) {
            $this->addError('bulkNumberingScheme', $exception->getMessage());

            return;
        }

        $this->property->refresh();
        $this->editingBuildingNumberingScheme = false;
        $this->showBulkForm = false;
        $this->resetBulkForm();
        session()->flash('success', 'Nomenclatura actualizada en '.$updatedCount.' unidades.');
        $this->resetPage();
    }

    public function addFloorRow(): void
    {
        $lastRow = $this->floorRows[array_key_last($this->floorRows)] ?? null;
        $lastRowFloor = (int) ($lastRow['floor'] ?? 0);

        $this->floorRows[] = [
            'floor' => (string) max(1, max($lastRowFloor, $this->maxFloorOnProperty()) + 1),
            'units' => '1',
        ];
    }

    public function removeFloorRow(int $index): void
    {
        if (count($this->floorRows) <= 1) {
            return;
        }

        unset($this->floorRows[$index]);
        $this->floorRows = array_values($this->floorRows);
    }

    public function generateBulkUnits(UnitNumberingService $numberingService): void
    {
        if (! (auth()->user()?->can('units.manage') ?? false)) {
            abort(403);
        }

        $propertyCode = trim((string) $this->property->code);
        if ($propertyCode === '') {
            $this->addError('floorRows', 'La propiedad debe tener un código para generar unidades.');

            return;
        }

        $lockedScheme = $numberingService->resolveScheme($this->property);
        if ($lockedScheme !== null && $lockedScheme !== $this->bulkNumberingScheme) {
            $this->addError(
                'bulkNumberingScheme',
                'Este edificio ya usa la nomenclatura «'.$numberingService->label($lockedScheme).'». Elimina todas las unidades o cambia la nomenclatura del edificio para usar otra.'
            );

            return;
        }

        $this->validate($this->bulkRules(), $this->bulkMessages());

        $definitions = $this->buildBulkUnitDefinitions();

        if ($definitions === []) {
            $this->addError('floorRows', 'Agrega al menos un piso con unidades válidas.');

            return;
        }

        $numbers = array_column($definitions, 'number');
        if (count($numbers) !== count(array_unique($numbers))) {
            $this->addError('floorRows', 'Hay números de unidad duplicados en la configuración.');

            return;
        }

        $codes = array_column($definitions, 'code');
        $newDefinitions = $this->filterNewBulkDefinitions($definitions);

        if ($newDefinitions === []) {
            $this->addError('floorRows', 'Todas las unidades de esta configuración ya existen.');

            return;
        }

        DB::transaction(function () use ($newDefinitions): void {
            $organizationId = auth()->user()?->organization_id;

            foreach ($newDefinitions as $definition) {
                Unit::query()->create([
                    'organization_id' => $organizationId,
                    'property_id' => $this->property->id,
                    'name' => $definition['name'],
                    'code' => $definition['code'],
                    'status' => 'active',
                    'kind' => Unit::KIND_APARTMENT,
                    'floor' => $definition['floor'],
                    'notes' => null,
                ]);
            }
        });

        $skippedCount = count($definitions) - count($newDefinitions);
        $message = count($newDefinitions).' unidades generadas correctamente.';
        if ($skippedCount > 0) {
            $message .= ' Se omitieron '.$skippedCount.' que ya existían.';
        }

        if ($lockedScheme === null) {
            $numberingService->lockScheme($this->property, $this->bulkNumberingScheme);
            $this->property->refresh();
        }

        session()->flash('success', $message);
        $this->cancelBulkForm();
        $this->resetPage();
    }

    public function deleteUnit(int $unitId): void
    {
        if (! (auth()->user()?->can('units.manage') ?? false)) {
            abort(403);
        }

        $unit = Unit::query()
            ->where('property_id', $this->property->id)
            ->findOrFail($unitId);

        if ($this->unitHasOperationalHistory($unit)) {
            $this->addError('delete', 'No puedes eliminar una unidad con contratos, cargos, gastos o documentos asociados.');

            return;
        }

        DB::transaction(fn () => $this->softDeleteUnit($unit));

        session()->flash('success', 'Unidad eliminada correctamente.');
        $this->resetPage();
    }

    public function confirmDeleteUnit(int $unitId): void
    {
        if (! (auth()->user()?->can('units.manage') ?? false)) {
            abort(403);
        }

        $unit = Unit::query()
            ->where('property_id', $this->property->id)
            ->findOrFail($unitId);

        $this->deleteConfirmType = 'single';
        $this->pendingDeleteUnitId = $unit->id;
        $this->pendingDeleteUnitName = $unit->name;
        $this->showDeleteConfirm = true;
    }

    public function confirmDeleteSelected(): void
    {
        if (! (auth()->user()?->can('units.manage') ?? false)) {
            abort(403);
        }

        if ($this->selectedUnitIds === []) {
            $this->addError('delete', 'Selecciona al menos una unidad para eliminar.');

            return;
        }

        $this->deleteConfirmType = 'bulk';
        $this->pendingDeleteUnitId = null;
        $this->pendingDeleteUnitName = null;
        $this->showDeleteConfirm = true;
    }

    public function cancelDeleteConfirm(): void
    {
        $this->showDeleteConfirm = false;
        $this->deleteConfirmType = '';
        $this->pendingDeleteUnitId = null;
        $this->pendingDeleteUnitName = null;
    }

    public function executeDeleteConfirm(): void
    {
        if ($this->deleteConfirmType === 'bulk') {
            $this->deleteSelectedUnits();
        } elseif ($this->deleteConfirmType === 'single' && $this->pendingDeleteUnitId !== null) {
            $this->deleteUnit($this->pendingDeleteUnitId);
        }

        $this->cancelDeleteConfirm();
    }

    public function selectAllDeletableInProperty(): void
    {
        if (! (auth()->user()?->can('units.manage') ?? false)) {
            abort(403);
        }

        $this->selectedUnitIds = $this->deletableUnitsQuery()
            ->pluck('id')
            ->map(fn (int $id): string => (string) $id)
            ->all();
    }

    public function clearSelection(): void
    {
        $this->selectedUnitIds = [];
    }

    public function togglePageSelection(): void
    {
        if (! (auth()->user()?->can('units.manage') ?? false)) {
            abort(403);
        }

        $pageDeletableIds = $this->getUnitsPaginator()
            ->getCollection()
            ->filter(fn (Unit $unit): bool => $this->unitIsDeletable($unit))
            ->pluck('id')
            ->map(fn (int $id): string => (string) $id)
            ->all();

        if ($pageDeletableIds === []) {
            return;
        }

        $allPageSelected = collect($pageDeletableIds)
            ->every(fn (string $id): bool => in_array($id, $this->selectedUnitIds, true));

        if ($allPageSelected) {
            $this->selectedUnitIds = array_values(array_diff($this->selectedUnitIds, $pageDeletableIds));

            return;
        }

        $this->selectedUnitIds = array_values(array_unique([
            ...$this->selectedUnitIds,
            ...$pageDeletableIds,
        ]));
    }

    public function deleteSelectedUnits(): void
    {
        if (! (auth()->user()?->can('units.manage') ?? false)) {
            abort(403);
        }

        $ids = collect($this->selectedUnitIds)
            ->map(fn (string|int $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            $this->addError('delete', 'Selecciona al menos una unidad para eliminar.');

            return;
        }

        $units = Unit::query()
            ->where('property_id', $this->property->id)
            ->whereIn('id', $ids)
            ->get();

        if ($units->count() !== count($ids)) {
            $this->addError('delete', 'Algunas unidades seleccionadas no son válidas.');

            return;
        }

        foreach ($units as $unit) {
            if ($this->unitHasOperationalHistory($unit)) {
                $this->addError('delete', 'No puedes eliminar unidades con contratos, cargos, gastos o documentos asociados.');

                return;
            }
        }

        DB::transaction(function () use ($units): void {
            foreach ($units as $unit) {
                $this->softDeleteUnit($unit);
            }
        });

        $deletedCount = $units->count();
        $this->selectedUnitIds = [];
        session()->flash('success', $deletedCount.' unidades eliminadas correctamente.');
        $this->resetPage();
    }

    public function render(): View
    {
        $units = $this->getUnitsPaginator();
        $pageDeletableIds = $units->getCollection()
            ->filter(fn (Unit $unit): bool => $this->unitIsDeletable($unit))
            ->pluck('id')
            ->map(fn (int $id): string => (string) $id)
            ->all();
        $allPageSelected = $pageDeletableIds !== []
            && collect($pageDeletableIds)->every(fn (string $id): bool => in_array($id, $this->selectedUnitIds, true));

        $bulkDefinitions = $this->showBulkForm ? $this->buildBulkUnitDefinitions() : [];
        $numberingService = app(UnitNumberingService::class);
        $lockedNumberingScheme = $numberingService->resolveScheme($this->property);

        return view('livewire.units.index', [
            'units' => $units,
            'canManageUnits' => auth()->user()?->can('units.manage') ?? false,
            'bulkPreview' => $this->filterNewBulkDefinitions($bulkDefinitions),
            'bulkPreviewTotal' => count($bulkDefinitions),
            'deletableInPropertyCount' => $this->deletableUnitsQuery()->count(),
            'pageDeletableIds' => $pageDeletableIds,
            'allPageSelected' => $allPageSelected,
            'lockedNumberingScheme' => $lockedNumberingScheme,
            'lockedNumberingSchemeLabel' => $lockedNumberingScheme !== null
                ? $numberingService->label($lockedNumberingScheme)
                : null,
            'hasPropertyUnits' => $this->property->units()->exists(),
        ])->layout('layouts.app', [
            'title' => 'Unidades',
        ]);
    }

    private function buildUnitCode(string $unitNumber): string
    {
        $cleanNumber = preg_replace('/\s+/', '', $unitNumber) ?? '';

        return TextCase::upperRequired(trim((string) $this->property->code).'-'.$cleanNumber);
    }

    /**
     * @return list<array{number: string, code: string, name: string, floor: string}>
     */
    private function buildBulkUnitDefinitions(): array
    {
        $definitions = [];
        $sequentialCounter = 1;

        foreach ($this->floorRows as $row) {
            $floor = (int) trim((string) ($row['floor'] ?? ''));
            $count = (int) trim((string) ($row['units'] ?? ''));

            if ($floor <= 0 || $count <= 0) {
                continue;
            }

            for ($unitIndex = 1; $unitIndex <= $count; $unitIndex++) {
                if ($this->bulkNumberingScheme === self::BULK_NUMBERING_SEQUENTIAL) {
                    $number = (string) $sequentialCounter;
                    $sequentialCounter++;
                } else {
                    $number = $floor.str_pad((string) $unitIndex, 2, '0', STR_PAD_LEFT);
                }

                $definitions[] = [
                    'number' => $number,
                    'code' => $this->buildUnitCode($number),
                    'name' => 'Departamento '.$number,
                    'floor' => (string) $floor,
                ];
            }
        }

        return $definitions;
    }

    /**
     * @param  list<array{number: string, code: string, name: string, floor: string}>  $definitions
     * @return list<array{number: string, code: string, name: string, floor: string}>
     */
    private function filterNewBulkDefinitions(array $definitions): array
    {
        if ($definitions === []) {
            return [];
        }

        $existingCodes = Unit::query()
            ->where('property_id', $this->property->id)
            ->whereIn('code', array_column($definitions, 'code'))
            ->pluck('code')
            ->all();
        $existingCodeLookup = array_fill_keys($existingCodes, true);

        return array_values(array_filter(
            $definitions,
            fn (array $definition): bool => ! isset($existingCodeLookup[$definition['code']]),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function bulkRules(): array
    {
        return [
            'bulkNumberingScheme' => [
                'required',
                Rule::in([self::BULK_NUMBERING_FLOOR_BASED, self::BULK_NUMBERING_SEQUENTIAL]),
            ],
            'floorRows' => ['required', 'array', 'min:1'],
            'floorRows.*.floor' => ['required', 'integer', 'min:1', 'max:200'],
            'floorRows.*.units' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function bulkMessages(): array
    {
        return [
            'bulkNumberingScheme.required' => 'Selecciona una nomenclatura para los números.',
            'bulkNumberingScheme.in' => 'La nomenclatura seleccionada no es válida.',
            'floorRows.required' => 'Agrega al menos un piso.',
            'floorRows.*.floor.required' => 'El número de piso es obligatorio.',
            'floorRows.*.floor.integer' => 'El piso debe ser un número entero.',
            'floorRows.*.floor.min' => 'El piso debe ser al menos 1.',
            'floorRows.*.units.required' => 'La cantidad de unidades es obligatoria.',
            'floorRows.*.units.integer' => 'La cantidad de unidades debe ser un número entero.',
            'floorRows.*.units.min' => 'Debe haber al menos 1 unidad por piso.',
        ];
    }

    private function resetBulkForm(): void
    {
        $this->floorRows = $this->suggestedInitialBulkFloorRows();
        $this->syncBulkNumberingSchemeFromProperty();
        $this->resetValidation();
    }

    private function syncBulkNumberingSchemeFromProperty(): void
    {
        $numberingService = app(UnitNumberingService::class);
        $numberingService->clearSchemeIfNoUnits($this->property);
        $this->property->refresh();

        $lockedScheme = $numberingService->resolveScheme($this->property);
        $this->bulkNumberingScheme = $lockedScheme ?? self::BULK_NUMBERING_FLOOR_BASED;
    }

    /**
     * @return list<array{floor: string, units: string}>
     */
    private function suggestedInitialBulkFloorRows(): array
    {
        if (! $this->property->units()->exists()) {
            return [['floor' => '1', 'units' => '1']];
        }

        return [[
            'floor' => (string) max(1, $this->maxFloorOnProperty() + 1),
            'units' => '1',
        ]];
    }

    private function maxFloorOnProperty(): int
    {
        return (int) (Unit::query()
            ->where('property_id', $this->property->id)
            ->whereNotNull('floor')
            ->where('floor', '!=', '')
            ->pluck('floor')
            ->map(fn (string $floor): int => (int) $floor)
            ->max() ?? 0);
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, Unit>
     */
    private function getUnitsPaginator()
    {
        return $this->unitsQuery()
            ->withCount([
                'contracts',
                'charges',
                'expenses',
                'documents',
            ])
            ->paginate(10);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Unit>
     */
    private function unitsQuery()
    {
        return Unit::query()
            ->where('property_id', $this->property->id)
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($innerQuery): void {
                    $innerQuery
                        ->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('code', 'like', '%'.$this->search.'%')
                        ->orWhere('floor', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderBy('name');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Unit>
     */
    private function deletableUnitsQuery()
    {
        return $this->unitsQuery()
            ->whereDoesntHave('contracts')
            ->whereDoesntHave('charges')
            ->whereDoesntHave('expenses')
            ->whereDoesntHave('documents');
    }

    private function unitIsDeletable(Unit $unit): bool
    {
        if ($unit->relationLoaded('contracts_count')) {
            return $unit->contracts_count === 0
                && $unit->charges_count === 0
                && $unit->expenses_count === 0
                && $unit->documents_count === 0;
        }

        return ! $this->unitHasOperationalHistory($unit);
    }

    private function softDeleteUnit(Unit $unit): void
    {
        $unit->update(['code' => null]);
        $unit->delete();

        app(UnitNumberingService::class)->clearSchemeIfNoUnits($this->property);
        $this->property->refresh();
    }

    private function unitHasOperationalHistory(Unit $unit): bool
    {
        return $unit->contracts()->exists()
            || $unit->charges()->exists()
            || $unit->expenses()->exists()
            || $unit->documents()->exists();
    }
}
