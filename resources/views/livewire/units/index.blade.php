<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Propiedad</p>
            <h1 class="text-2xl font-semibold tracking-tight">{{ $property->name }}</h1>
            <p class="mt-1 text-sm text-slate-600">Gestión de unidades asociadas.</p>
        </div>
        <div class="flex gap-2">
            <a
                href="{{ route('properties.index') }}"
                class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                Volver a propiedades
            </a>
            @if ($canManageUnits)
                <button
                    type="button"
                    wire:click="startBulkGenerate"
                    class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    Gestionar unidades
                </button>
            @endif
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-3">
            <div class="md:col-span-2">
                <label for="unit-search" class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">
                    Buscar
                </label>
                <input
                    id="unit-search"
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Nombre, código o piso..."
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                >
            </div>
            <div>
                <label for="unit-status-filter" class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">
                    Estado
                </label>
                <select
                    id="unit-status-filter"
                    wire:model.live="statusFilter"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                >
                    <option value="">Todos</option>
                    <option value="active">Activo</option>
                    <option value="inactive">Inactivo</option>
                </select>
            </div>
        </div>
    </div>

    @if ($canManageUnits)
        <x-ui.modal
            :open="$showBulkForm"
            title="Generar unidades"
            aria-label="Generar unidades"
            max-width="2xl"
            close-action="cancelBulkForm"
        >
            <p class="mb-4 text-sm text-slate-600">
                Define los pisos y cuántas unidades tiene cada uno. Si te equivocaste en la cantidad, vuelve a generar con el total correcto en el mismo piso: solo se crearán las que falten.
            </p>

            <fieldset class="mb-4 space-y-2">
                <legend class="mb-2 block text-sm font-medium text-slate-700">Nomenclatura de números</legend>

                @if ($lockedNumberingScheme && ! $editingBuildingNumberingScheme)
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <p class="text-sm font-medium text-slate-900">{{ $lockedNumberingSchemeLabel }}</p>
                        <p class="mt-1 text-xs text-slate-600">
                            Este edificio ya tiene unidades con esta nomenclatura. No puedes generar con otra a menos que elimines todas las unidades (sin contratos ni movimientos) o cambies la nomenclatura del edificio.
                        </p>
                        <button
                            type="button"
                            wire:click="startEditingBuildingNumberingScheme"
                            class="mt-3 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Cambiar nomenclatura del edificio
                        </button>
                    </div>
                    <input type="hidden" wire:model="bulkNumberingScheme">
                @elseif ($editingBuildingNumberingScheme)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                        La nueva nomenclatura se aplicará a <strong>todas</strong> las unidades del edificio, incluidas las que ya tienen contratos o movimientos.
                    </div>
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 transition has-[:checked]:border-slate-400 has-[:checked]:bg-slate-50">
                        <input
                            type="radio"
                            wire:model.live="bulkNumberingScheme"
                            value="floor_based"
                            class="mt-0.5 border-slate-300 text-slate-900 focus:ring-slate-500"
                        >
                        <span>
                            <span class="block text-sm font-medium text-slate-900">Por piso (101, 102…)</span>
                            <span class="mt-0.5 block text-xs text-slate-500">Piso 1 → 101, 102… · Piso 2 → 201, 202…</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 transition has-[:checked]:border-slate-400 has-[:checked]:bg-slate-50">
                        <input
                            type="radio"
                            wire:model.live="bulkNumberingScheme"
                            value="sequential"
                            class="mt-0.5 border-slate-300 text-slate-900 focus:ring-slate-500"
                        >
                        <span>
                            <span class="block text-sm font-medium text-slate-900">Consecutivos (1, 2, 3…)</span>
                            <span class="mt-0.5 block text-xs text-slate-500">Numeración global sin prefijo de piso en el número.</span>
                        </span>
                    </label>
                    <div class="flex flex-wrap gap-2 pt-1">
                        <button
                            type="button"
                            wire:click="applyBuildingNumberingScheme"
                            wire:loading.attr="disabled"
                            wire:target="applyBuildingNumberingScheme"
                            class="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800 disabled:opacity-60"
                        >
                            <span wire:loading.remove wire:target="applyBuildingNumberingScheme">Aplicar a todas las unidades</span>
                            <span wire:loading wire:target="applyBuildingNumberingScheme">Aplicando…</span>
                        </button>
                        <button
                            type="button"
                            wire:click="cancelEditingBuildingNumberingScheme"
                            wire:loading.attr="disabled"
                            wire:target="applyBuildingNumberingScheme"
                            class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-60"
                        >
                            Cancelar
                        </button>
                    </div>
                @else
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 transition has-[:checked]:border-slate-400 has-[:checked]:bg-slate-50">
                        <input
                            type="radio"
                            wire:model.live="bulkNumberingScheme"
                            value="floor_based"
                            class="mt-0.5 border-slate-300 text-slate-900 focus:ring-slate-500"
                        >
                        <span>
                            <span class="block text-sm font-medium text-slate-900">Por piso (101, 102…)</span>
                            <span class="mt-0.5 block text-xs text-slate-500">Piso 1 → 101, 102… · Piso 2 → 201, 202…</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 transition has-[:checked]:border-slate-400 has-[:checked]:bg-slate-50">
                        <input
                            type="radio"
                            wire:model.live="bulkNumberingScheme"
                            value="sequential"
                            class="mt-0.5 border-slate-300 text-slate-900 focus:ring-slate-500"
                        >
                        <span>
                            <span class="block text-sm font-medium text-slate-900">Consecutivos (1, 2, 3…)</span>
                            <span class="mt-0.5 block text-xs text-slate-500">Numeración global sin prefijo de piso en el número.</span>
                        </span>
                    </label>
                @endif

                @error('bulkNumberingScheme') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </fieldset>

            @if (! $editingBuildingNumberingScheme)
            <div class="space-y-3">
                @foreach ($floorRows as $index => $row)
                    <div
                        class="grid grid-cols-1 gap-3 rounded-lg border border-slate-200 p-3 md:grid-cols-12 md:items-end md:gap-3"
                        wire:key="floor-row-{{ $index }}"
                    >
                        <div class="md:col-span-5">
                            <label class="mb-1 block text-sm font-medium text-slate-700">Piso *</label>
                            <input
                                type="number"
                                min="1"
                                wire:model.live="floorRows.{{ $index }}.floor"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                            >
                            @error('floorRows.'.$index.'.floor') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-5">
                            <label class="mb-1 block text-sm font-medium text-slate-700">Unidades *</label>
                            <input
                                type="number"
                                min="1"
                                wire:model.live="floorRows.{{ $index }}.units"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                            >
                            @error('floorRows.'.$index.'.units') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <button
                                type="button"
                                wire:click="removeFloorRow({{ $index }})"
                                class="w-full whitespace-nowrap rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
                                @if (count($floorRows) <= 1) disabled @endif
                            >
                                Quitar
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            @error('floorRows') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

            <button
                type="button"
                wire:click="addFloorRow"
                class="mt-3 rounded-md border border-dashed border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
            >
                + Agregar piso
            </button>

            @if ($bulkPreview !== [])
                <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Vista previa ({{ count($bulkPreview) }} nuevas
                        @if ($bulkPreviewTotal > count($bulkPreview))
                            · {{ $bulkPreviewTotal - count($bulkPreview) }} ya existen y se omitirán
                        @endif
                        )
                    </p>
                    <p class="mt-2 text-sm text-slate-700">
                        {{ collect($bulkPreview)->take(8)->pluck('code')->join(', ') }}
                        @if (count($bulkPreview) > 8)
                            … y {{ count($bulkPreview) - 8 }} más
                        @endif
                    </p>
                </div>
            @endif

            <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
                <button
                    type="button"
                    wire:click="cancelBulkForm"
                    class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    wire:click="generateBulkUnits"
                    class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
                >
                    Generar {{ count($bulkPreview) }} unidades
                </button>
            </div>
            @endif
        </x-ui.modal>

        <x-ui.confirm-modal
            :open="$showDeleteConfirm"
            :title="$deleteConfirmType === 'bulk' ? 'Eliminar unidades seleccionadas' : 'Eliminar unidad'"
            confirm-action="executeDeleteConfirm"
            cancel-action="cancelDeleteConfirm"
            :confirm-label="$deleteConfirmType === 'bulk' ? 'Eliminar unidades' : 'Eliminar unidad'"
            aria-label="Confirmar eliminación de unidades"
        >
            @if ($deleteConfirmType === 'bulk')
                <p class="text-slate-700">
                    Vas a eliminar <span class="font-semibold text-slate-900">{{ count($selectedUnitIds) }}</span>
                    {{ count($selectedUnitIds) === 1 ? 'unidad' : 'unidades' }} de esta propiedad.
                </p>
                <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                    Solo se eliminan unidades sin contratos ni movimientos. Podrás volver a crear unidades con los mismos números si fue un error.
                </p>
            @else
                <p class="text-slate-700">
                    Vas a eliminar <span class="font-semibold text-slate-900">{{ $pendingDeleteUnitName }}</span> de esta propiedad.
                </p>
                <p class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                    Podrás volver a crear una unidad con el mismo número si fue un error.
                </p>
            @endif
        </x-ui.confirm-modal>
    @endif

    @error('delete')
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $message }}
        </div>
    @enderror

    @if ($canManageUnits && ($deletableInPropertyCount > 0 || count($selectedUnitIds) > 0))
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <p class="text-sm text-slate-700">
                @if (count($selectedUnitIds) > 0)
                    {{ count($selectedUnitIds) }} {{ count($selectedUnitIds) === 1 ? 'unidad seleccionada' : 'unidades seleccionadas' }}
                @else
                    {{ $deletableInPropertyCount }} {{ $deletableInPropertyCount === 1 ? 'unidad eliminable' : 'unidades eliminables' }} con los filtros actuales
                @endif
            </p>
            <div class="flex flex-wrap gap-2">
                @if (count($selectedUnitIds) > 0)
                    <button
                        type="button"
                        wire:click="clearSelection"
                        class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                    >
                        Limpiar selección
                    </button>
                    <button
                        type="button"
                        wire:click="confirmDeleteSelected"
                        class="rounded-md border border-red-300 bg-white px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50"
                    >
                        Eliminar seleccionadas
                    </button>
                @endif
                <button
                    type="button"
                    wire:click="selectAllDeletableInProperty"
                    class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                >
                    Seleccionar todas las eliminables
                </button>
            </div>
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        @if ($canManageUnits)
                            <th class="px-4 py-3 w-10">
                                @if ($pageDeletableIds !== [])
                                    <input
                                        type="checkbox"
                                        wire:click="togglePageSelection"
                                        @checked($allPageSelected)
                                        class="rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                                        aria-label="Seleccionar unidades de esta página"
                                    >
                                @endif
                            </th>
                        @endif
                        <th class="px-4 py-3">Código</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($units as $unit)
                        @php
                            $unitIsDeletable = $unit->contracts_count === 0
                                && $unit->charges_count === 0
                                && $unit->expenses_count === 0
                                && $unit->documents_count === 0;
                        @endphp
                        <tr wire:key="unit-row-{{ $unit->id }}">
                            @if ($canManageUnits)
                                <td class="px-4 py-3">
                                    @if ($unitIsDeletable)
                                        <input
                                            type="checkbox"
                                            wire:model="selectedUnitIds"
                                            value="{{ $unit->id }}"
                                            class="rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                                            aria-label="Seleccionar {{ $unit->code ?: 'unidad' }}"
                                        >
                                    @endif
                                </td>
                            @endif
                            <td class="px-4 py-3">
                                <p class="font-medium uppercase text-slate-900">{{ $unit->code ?: 'Sin código' }}</p>
                                @if ($lockedNumberingScheme === 'sequential' && $unit->floor)
                                    <p class="text-xs text-slate-500">Piso {{ $unit->floor }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-medium {{ $unit->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }}">
                                    {{ $unit->status === 'active' ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    @if ($canManageUnits && $unitIsDeletable)
                                        <button
                                            type="button"
                                            wire:click="confirmDeleteUnit({{ $unit->id }})"
                                            class="rounded-md border border-red-300 p-1.5 text-red-700 hover:bg-red-50"
                                            aria-label="Eliminar {{ $unit->code ?: 'unidad' }}"
                                            title="Eliminar"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canManageUnits ? 4 : 3 }}" class="px-4 py-8 text-center text-sm text-slate-500">
                                No hay unidades con los filtros actuales.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 bg-slate-50 px-4 py-3">
            {{ $units->links() }}
        </div>
    </div>
</section>
