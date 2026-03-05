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
                    wire:click="startCreate"
                    class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
                >
                    Nueva unidad
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

    @if ($showForm && $canManageUnits)
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">
                {{ $editingId ? 'Editar unidad' : 'Crear unidad' }}
            </h2>

            <form wire:submit="save" class="mt-4 grid gap-4 md:grid-cols-2">
                <div>
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

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Piso/Nivel</label>
                    <input type="text" wire:model.blur="floor" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('floor') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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
                        <th class="px-4 py-3">Unidad</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Contratos activos</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($units as $unit)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ $unit->name }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ $unit->code ?: 'Sin código' }}
                                    @if ($unit->floor)
                                        · Piso {{ $unit->floor }}
                                    @endif
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-medium {{ $unit->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }}">
                                    {{ $unit->status === 'active' ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-slate-700">{{ $unit->active_contracts_count }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end">
                                    @if ($canManageUnits)
                                        <button
                                            type="button"
                                            wire:click="startEdit({{ $unit->id }})"
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
