<section class="space-y-6">
    <x-ui.page-header
        :title="$property->name"
        :description="__('catalog.units.description')"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('properties.index') }}" variant="secondary">
                {{ __('common.back_to_properties') }}
            </x-ui.button>
            @if ($canManageUnits)
                <x-ui.button type="button" variant="secondary" wire:click="startBulkGenerate">
                    {{ __('catalog.units.manage_units') }}
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="true" class="!p-4">
        <div class="grid gap-3 md:grid-cols-3">
            <div class="md:col-span-2">
                <x-ui.input
                    id="unit-search"
                    :label="__('common.search')"
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    :placeholder="__('catalog.units.search_placeholder')"
                />
            </div>

            <x-ui.select id="unit-status-filter" :label="__('common.status')" wire:model.live="statusFilter">
                <option value="">{{ __('common.all') }}</option>
                <option value="active">{{ __('common.active') }}</option>
                <option value="inactive">{{ __('common.inactive') }}</option>
            </x-ui.select>
        </div>
    </x-ui.card>

    @if ($canManageUnits)
        <x-ui.modal
            :open="$showBulkForm"
            :title="__('catalog.units.generate_units')"
            :aria-label="__('catalog.units.generate_units')"
            max-width="2xl"
            close-action="cancelBulkForm"
        >
            <p class="mb-4 text-sm text-slate-600">
                {{ __('catalog.units.generate_units_description') }}
            </p>

            <fieldset class="mb-4 space-y-2">
                <legend class="mb-2 block text-sm font-medium text-slate-700">{{ __('catalog.units.numbering_legend') }}</legend>

                @if ($lockedNumberingScheme && ! $editingBuildingNumberingScheme)
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <p class="text-sm font-medium text-slate-900">{{ $lockedNumberingSchemeLabel }}</p>
                        <p class="mt-1 text-xs text-slate-600">
                            {{ __('catalog.units.numbering_locked_description') }}
                        </p>
                        <x-ui.button
                            type="button"
                            variant="secondary"
                            size="sm"
                            wire:click="startEditingBuildingNumberingScheme"
                            class="mt-3"
                        >
                            {{ __('catalog.units.change_building_numbering') }}
                        </x-ui.button>
                    </div>
                    <input type="hidden" wire:model="bulkNumberingScheme">
                @elseif ($editingBuildingNumberingScheme)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                        {!! __('catalog.units.numbering_apply_warning') !!}
                    </div>
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 transition has-[:checked]:border-slate-400 has-[:checked]:bg-slate-50">
                        <input
                            type="radio"
                            wire:model.live="bulkNumberingScheme"
                            value="floor_based"
                            class="mt-0.5 border-slate-300 text-slate-900 focus:ring-slate-500"
                        >
                        <span>
                            <span class="block text-sm font-medium text-slate-900">{{ __('catalog.units.numbering_floor_based') }}</span>
                            <span class="mt-0.5 block text-xs text-slate-500">{{ __('catalog.units.numbering_floor_based_hint') }}</span>
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
                            <span class="block text-sm font-medium text-slate-900">{{ __('catalog.units.numbering_sequential') }}</span>
                            <span class="mt-0.5 block text-xs text-slate-500">{{ __('catalog.units.numbering_sequential_hint') }}</span>
                        </span>
                    </label>
                    <div class="flex flex-wrap gap-2 pt-1">
                        <x-ui.button
                            type="button"
                            size="sm"
                            wire:click="applyBuildingNumberingScheme"
                            wire:loading.attr="disabled"
                            wire:target="applyBuildingNumberingScheme"
                        >
                            <span wire:loading.remove wire:target="applyBuildingNumberingScheme">{{ __('catalog.units.apply_to_all_units') }}</span>
                            <span wire:loading wire:target="applyBuildingNumberingScheme">{{ __('catalog.units.applying') }}</span>
                        </x-ui.button>
                        <x-ui.button
                            type="button"
                            variant="secondary"
                            size="sm"
                            wire:click="cancelEditingBuildingNumberingScheme"
                            wire:loading.attr="disabled"
                            wire:target="applyBuildingNumberingScheme"
                        >
                            {{ __('common.cancel') }}
                        </x-ui.button>
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
                            <span class="block text-sm font-medium text-slate-900">{{ __('catalog.units.numbering_floor_based') }}</span>
                            <span class="mt-0.5 block text-xs text-slate-500">{{ __('catalog.units.numbering_floor_based_hint') }}</span>
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
                            <span class="block text-sm font-medium text-slate-900">{{ __('catalog.units.numbering_sequential') }}</span>
                            <span class="mt-0.5 block text-xs text-slate-500">{{ __('catalog.units.numbering_sequential_hint') }}</span>
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
                            <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('common.floor') }} *</label>
                            <input
                                type="number"
                                min="1"
                                wire:model.live="floorRows.{{ $index }}.floor"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                            >
                            @error('floorRows.'.$index.'.floor') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-5">
                            <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('catalog.units.units_per_floor') }} *</label>
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
                                {{ __('common.remove') }}
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
                {{ __('catalog.units.add_floor') }}
            </button>

            @if ($bulkPreview !== [])
                <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {{ __('catalog.units.preview_title', ['count' => count($bulkPreview)]) }}
                        @if ($bulkPreviewTotal > count($bulkPreview))
                            {{ __('catalog.units.preview_existing', ['count' => $bulkPreviewTotal - count($bulkPreview)]) }}
                        @endif
                        )
                    </p>
                    <p class="mt-2 text-sm text-slate-700">
                        {{ collect($bulkPreview)->take(8)->pluck('code')->join(', ') }}
                        @if (count($bulkPreview) > 8)
                            {{ __('catalog.units.preview_and_more', ['count' => count($bulkPreview) - 8]) }}
                        @endif
                    </p>
                </div>
            @endif

            <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancelBulkForm">
                    {{ __('common.cancel') }}
                </x-ui.button>
                <x-ui.button type="button" wire:click="generateBulkUnits">
                    {{ __('catalog.units.generate_count', ['count' => count($bulkPreview)]) }}
                </x-ui.button>
            </div>
            @endif
        </x-ui.modal>

        <x-ui.confirm-modal
            :open="$showDeleteConfirm"
            :title="$deleteConfirmType === 'bulk' ? __('catalog.units.delete_selected_units') : __('catalog.units.delete_unit')"
            confirm-action="executeDeleteConfirm"
            cancel-action="cancelDeleteConfirm"
            :confirm-label="$deleteConfirmType === 'bulk' ? __('catalog.units.delete_units_confirm') : __('catalog.units.delete_unit_confirm')"
            :aria-label="__('catalog.units.confirm_delete_aria')"
        >
            @if ($deleteConfirmType === 'bulk')
                <p class="text-slate-700">
                    {{ __('catalog.units.delete_bulk_message', [
                        'count' => count($selectedUnitIds),
                        'unit_label' => count($selectedUnitIds) === 1 ? __('catalog.units.unit_singular') : __('catalog.units.unit_plural'),
                    ]) }}
                </p>
                <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                    {{ __('catalog.units.delete_bulk_warning') }}
                </p>
            @else
                <p class="text-slate-700">
                    {{ __('catalog.units.delete_single_message', ['name' => $pendingDeleteUnitName]) }}
                </p>
                <p class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                    {{ __('catalog.units.delete_single_hint') }}
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
                    {{ count($selectedUnitIds) === 1
                        ? __('catalog.units.selected_singular', ['count' => count($selectedUnitIds)])
                        : __('catalog.units.selected_plural', ['count' => count($selectedUnitIds)]) }}
                @else
                    {{ $deletableInPropertyCount === 1
                        ? __('catalog.units.deletable_singular', ['count' => $deletableInPropertyCount])
                        : __('catalog.units.deletable_plural', ['count' => $deletableInPropertyCount]) }}
                @endif
            </p>
            <div class="flex flex-wrap gap-2">
                @if (count($selectedUnitIds) > 0)
                    <button
                        type="button"
                        wire:click="clearSelection"
                        class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                    >
                        {{ __('catalog.units.clear_selection') }}
                    </button>
                    <button
                        type="button"
                        wire:click="confirmDeleteSelected"
                        class="rounded-md border border-red-300 bg-white px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50"
                    >
                        {{ __('catalog.units.delete_selected') }}
                    </button>
                @endif
                <button
                    type="button"
                    wire:click="selectAllDeletableInProperty"
                    class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                >
                    {{ __('catalog.units.select_all_deletable') }}
                </button>
            </div>
        </div>
    @endif

    <x-ui.table>
        <x-slot:head>
            @if ($canManageUnits)
                <th class="px-4 py-3 w-10">
                    @if ($pageDeletableIds !== [])
                        <input
                            type="checkbox"
                            wire:click="togglePageSelection"
                            @checked($allPageSelected)
                            class="rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                            aria-label="{{ __('catalog.units.select_page_units') }}"
                        >
                    @endif
                </th>
            @endif
            <th class="px-4 py-3">{{ __('common.code') }}</th>
            <th class="px-4 py-3">{{ __('common.status') }}</th>
            <th class="px-4 py-3 text-right">{{ __('common.actions') }}</th>
        </x-slot:head>
        <x-slot:body>
            @forelse ($units as $unit)
                @php
                    $unitIsDeletable = $unit->contracts_count === 0
                        && $unit->charges_count === 0
                        && $unit->expenses_count === 0
                        && $unit->documents_count === 0
                        && $unit->inventory_items_count === 0;
                @endphp
                <tr wire:key="unit-row-{{ $unit->id }}" class="transition hover:bg-slate-50/80">
                    @if ($canManageUnits)
                        <td class="px-4 py-3">
                            @if ($unitIsDeletable)
                                <input
                                    type="checkbox"
                                    wire:model="selectedUnitIds"
                                    value="{{ $unit->id }}"
                                    class="rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                                    aria-label="{{ __('catalog.units.select_unit', ['label' => $unit->code ?: __('common.unit')]) }}"
                                >
                            @endif
                        </td>
                    @endif
                    <td class="px-4 py-3">
                        <a
                            href="{{ route('properties.units.show', ['property' => $property, 'unit' => $unit]) }}"
                            class="font-medium uppercase text-blue-700 hover:underline"
                        >
                            {{ $unit->code ?: __('common.no_code') }}
                        </a>
                        @if ($lockedNumberingScheme === 'sequential' && $unit->floor)
                            <p class="text-xs text-slate-500">{{ __('catalog.units.floor_label', ['floor' => $unit->floor]) }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <x-ui.badge :variant="$unit->status === 'active' ? 'success' : 'neutral'">
                            {{ $unit->status === 'active' ? __('common.active') : __('common.inactive') }}
                        </x-ui.badge>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end gap-2">
                            <x-ui.button
                                href="{{ route('properties.units.show', ['property' => $property, 'unit' => $unit]) }}"
                                variant="secondary"
                                size="sm"
                            >
                                {{ __('inventory.view_inventory') }}
                            </x-ui.button>
                            @if ($canManageUnits && $unitIsDeletable)
                                <button
                                    type="button"
                                    wire:click="confirmDeleteUnit({{ $unit->id }})"
                                    class="rounded-md border border-red-300 p-1.5 text-red-700 hover:bg-red-50"
                                    aria-label="{{ __('catalog.units.delete_unit_aria', ['label' => $unit->code ?: __('common.unit')]) }}"
                                    title="{{ __('common.delete') }}"
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
                <x-ui.empty-state :title="__('catalog.units.empty')" :colspan="$canManageUnits ? 4 : 3" />
            @endforelse
        </x-slot:body>
        <x-slot:footer>
            <div class="bg-slate-50/80 px-4 py-3">
                {{ $units->links() }}
            </div>
        </x-slot:footer>
    </x-ui.table>
</section>
