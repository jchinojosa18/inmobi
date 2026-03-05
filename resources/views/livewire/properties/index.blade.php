<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Propiedades</h1>
            <p class="mt-1 text-sm text-slate-600">Catálogo base de inmuebles por organización.</p>
        </div>
        @if ($canManageProperties)
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    wire:click="startCreate"
                    class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
                >
                    Nuevo edificio/propiedad
                </button>
                <a
                    href="{{ route('houses.create') }}"
                    class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    Nueva casa
                </a>
            </div>
        @endif
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-3">
            <div class="md:col-span-2">
                <label for="property-search" class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">
                    Buscar
                </label>
                <input
                    id="property-search"
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Nombre, código o dirección..."
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                >
            </div>
            <div>
                <label for="property-status-filter" class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">
                    Estado
                </label>
                <select
                    id="property-status-filter"
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

    @if ($showForm && $canManageProperties)
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">
                {{ $editingId ? 'Editar propiedad' : 'Crear propiedad' }}
            </h2>

            <form wire:submit="save" class="mt-4 grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nombre *</label>
                    <input type="text" wire:model.blur="name" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Código</label>
                    <input type="text" wire:model.blur="code" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
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
                    <input type="text" wire:model.blur="address" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Notas</label>
                    <textarea wire:model.blur="notes" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                    @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2 flex flex-wrap items-center justify-end gap-2">
                    <button
                        type="button"
                        wire:click="cancelForm"
                        class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
                    >
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Propiedad</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Unidades</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($properties as $property)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ $property->name }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ $property->code ?: 'Sin código' }}
                                    @if ($property->address)
                                        · {{ $property->address }}
                                    @endif
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $property->kind === \App\Models\Property::KIND_STANDALONE_HOUSE ? 'Casa standalone' : 'Edificio/propiedad' }}
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-medium {{ $property->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }}">
                                    {{ $property->status === 'active' ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-slate-700">{{ $property->units_count }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    @if ($property->kind === \App\Models\Property::KIND_STANDALONE_HOUSE)
                                        <a
                                            href="{{ route('houses.show', $property) }}"
                                            class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                        >
                                            Casa
                                        </a>
                                    @else
                                        <a
                                            href="{{ route('properties.units.index', $property) }}"
                                            class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                        >
                                            Unidades
                                        </a>
                                    @endif
                                    @if ($canManageProperties)
                                        <button
                                            type="button"
                                            wire:click="startEdit({{ $property->id }})"
                                            class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                        >
                                            Editar
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">
                                No hay propiedades con los filtros actuales.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 bg-slate-50 px-4 py-3">
            {{ $properties->links() }}
        </div>
    </div>
</section>
