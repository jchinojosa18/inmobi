<section class="space-y-6">
    <x-ui.page-header
        :title="__('catalog.properties.title')"
        :description="__('catalog.properties.description')"
    >
        <x-slot:actions>
            @if ($canManageProperties)
                <x-ui.button type="button" wire:click="$dispatch('open-property-create')">
                    {{ __('catalog.properties.new_property') }}
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="true" class="!p-4">
        <div class="grid gap-3 md:grid-cols-3">
            <div class="md:col-span-2">
                <x-ui.input
                    id="property-search"
                    :label="__('common.search')"
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    :placeholder="__('catalog.properties.search_placeholder')"
                />
            </div>

            <x-ui.select id="property-status-filter" :label="__('common.status')" wire:model.live="statusFilter">
                <option value="">{{ __('common.all') }}</option>
                <option value="active">{{ __('common.active') }}</option>
                <option value="inactive">{{ __('common.inactive') }}</option>
            </x-ui.select>
        </div>
    </x-ui.card>

    @if ($canManageProperties)
        <x-ui.modal
            :open="$showForm"
            :title="__('catalog.properties.edit_property')"
            :aria-label="__('catalog.properties.edit_property')"
            max-width="2xl"
        >
            <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('common.name') }} *</label>
                    <input type="text" wire:model="name" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm uppercase">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('common.code') }}</label>
                    <input type="text" wire:model="code" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm uppercase">
                    @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('common.status') }} *</label>
                    <select wire:model="formStatus" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="active">{{ __('common.active') }}</option>
                        <option value="inactive">{{ __('common.inactive') }}</option>
                    </select>
                    @error('formStatus') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('common.address') }}</label>
                    <input type="text" wire:model="address" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm uppercase">
                    @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('common.notes') }}</label>
                    <textarea wire:model.blur="notes" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                    @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2 flex flex-wrap items-center justify-end gap-2">
                    <x-ui.button type="button" variant="secondary" wire:click="cancelForm">
                        {{ __('common.cancel') }}
                    </x-ui.button>
                    <x-ui.button type="submit">
                        {{ __('common.save') }}
                    </x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    <x-ui.table>
        <x-slot:head>
            <th class="px-4 py-3">{{ __('common.property') }}</th>
            <th class="px-4 py-3">{{ __('common.status') }}</th>
            <th class="px-4 py-3 text-right">{{ __('common.units') }}</th>
            <th class="px-4 py-3 text-right">{{ __('common.actions') }}</th>
        </x-slot:head>
        <x-slot:body>
            @forelse ($properties as $property)
                <tr wire:key="property-row-{{ $property->id }}" class="transition hover:bg-slate-50/80">
                    <td class="px-4 py-3">
                        <p class="font-medium text-slate-900">{{ $property->name }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $property->code ?: __('common.no_code') }}
                            @if ($property->address)
                                · {{ $property->address }}
                            @endif
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ $property->kindLabel() }}
                        </p>
                    </td>
                    <td class="px-4 py-3">
                        <x-ui.badge :variant="$property->status === 'active' ? 'success' : 'neutral'">
                            {{ $property->status === 'active' ? __('common.active') : __('common.inactive') }}
                        </x-ui.badge>
                    </td>
                    <td class="px-4 py-3 text-right font-medium text-slate-700">{{ $property->units_count }}</td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end gap-2">
                            @if ($property->isStandaloneEntity())
                                <x-ui.button href="{{ route('houses.show', $property) }}" variant="secondary" size="sm">
                                    {{ __('common.view') }}
                                </x-ui.button>
                            @else
                                <x-ui.button href="{{ route('properties.units.index', $property) }}" variant="secondary" size="sm">
                                    {{ __('common.units') }}
                                </x-ui.button>
                            @endif
                            @if ($canManageProperties)
                                <x-ui.button type="button" variant="secondary" size="sm" wire:click="startEdit({{ $property->id }})">
                                    {{ __('common.edit') }}
                                </x-ui.button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <x-ui.empty-state :title="__('catalog.properties.empty')" :colspan="4" />
            @endforelse
        </x-slot:body>
        <x-slot:footer>
            <div class="bg-slate-50/80 px-4 py-3">
                {{ $properties->links() }}
            </div>
        </x-slot:footer>
    </x-ui.table>

    @if ($canManageProperties)
        <livewire:properties.create-modal />
    @endif
</section>
