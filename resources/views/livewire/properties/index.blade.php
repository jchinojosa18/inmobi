<section class="space-y-6">
    <x-ui.page-header
        title="Propiedades"
        description="Catálogo base de inmuebles por organización."
    >
        <x-slot:actions>
            @if ($canManageProperties)
                <x-ui.button type="button" wire:click="$dispatch('open-property-create')">
                    Nuevo inmueble
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="true" class="!p-4">
        <div class="grid gap-3 md:grid-cols-3">
            <div class="md:col-span-2">
                <x-ui.input
                    id="property-search"
                    label="Buscar"
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Nombre, código o dirección..."
                />
            </div>

            <x-ui.select id="property-status-filter" label="Estado" wire:model.live="statusFilter">
                <option value="">Todos</option>
                <option value="active">Activo</option>
                <option value="inactive">Inactivo</option>
            </x-ui.select>
        </div>
    </x-ui.card>

    @if ($canManageProperties)
        <x-ui.modal
            :open="$showForm"
            :title="'Editar propiedad'"
            aria-label="Editar propiedad"
            max-width="2xl"
        >
            <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nombre *</label>
                    <input type="text" wire:model.live="name" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm uppercase">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Código</label>
                    <input type="text" wire:model.live="code" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm uppercase">
                    @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Estado *</label>
                    <select wire:model="formStatus" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="active">Activo</option>
                        <option value="inactive">Inactivo</option>
                    </select>
                    @error('formStatus') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Dirección</label>
                    <input type="text" wire:model.live="address" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm uppercase">
                    @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Notas</label>
                    <textarea wire:model.blur="notes" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                    @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2 flex flex-wrap items-center justify-end gap-2">
                    <x-ui.button type="button" variant="secondary" wire:click="cancelForm">
                        Cancelar
                    </x-ui.button>
                    <x-ui.button type="submit">
                        Guardar
                    </x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    <x-ui.table>
        <x-slot:head>
            <th class="px-4 py-3">Propiedad</th>
            <th class="px-4 py-3">Estado</th>
            <th class="px-4 py-3 text-right">Unidades</th>
            <th class="px-4 py-3 text-right">Acciones</th>
        </x-slot:head>
        <x-slot:body>
            @forelse ($properties as $property)
                <tr class="transition hover:bg-slate-50/80">
                    <td class="px-4 py-3">
                        <p class="font-medium text-slate-900">{{ $property->name }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $property->code ?: 'Sin código' }}
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
                            {{ $property->status === 'active' ? 'Activo' : 'Inactivo' }}
                        </x-ui.badge>
                    </td>
                    <td class="px-4 py-3 text-right font-medium text-slate-700">{{ $property->units_count }}</td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end gap-2">
                            @if ($property->isStandaloneEntity())
                                <x-ui.button href="{{ route('houses.show', $property) }}" variant="secondary" size="sm">
                                    Ver
                                </x-ui.button>
                            @else
                                <x-ui.button href="{{ route('properties.units.index', $property) }}" variant="secondary" size="sm">
                                    Unidades
                                </x-ui.button>
                            @endif
                            @if ($canManageProperties)
                                <x-ui.button type="button" variant="secondary" size="sm" wire:click="startEdit({{ $property->id }})">
                                    Editar
                                </x-ui.button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <x-ui.empty-state title="No hay propiedades con los filtros actuales." :colspan="4" />
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
