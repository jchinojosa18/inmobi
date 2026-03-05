<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Plazas</h1>
            <p class="mt-1 text-sm text-slate-600">Administración de plazas por organización (multi-ciudad).</p>
        </div>
        @if ($isAdmin)
            <button
                type="button"
                wire:click="startCreate"
                class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
            >
                Nueva plaza
            </button>
        @endif
    </div>

    @if ($singlePlaza)
        <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            Solo tienes una plaza (Principal). Agrega otra si administras varias ciudades.
        </div>
    @endif

    @error('delete')
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $message }}
        </div>
    @enderror

    @if ($showForm && $isAdmin)
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">
                {{ $editingId ? 'Editar plaza' : 'Nueva plaza' }}
            </h2>

            <form wire:submit="save" class="mt-4 grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nombre</label>
                    <input type="text" wire:model.blur="nombre" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('nombre') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Ciudad</label>
                    <input type="text" wire:model.blur="ciudad" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('ciudad') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Timezone</label>
                    <input type="text" wire:model.blur="timezone" placeholder="America/Tijuana" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('timezone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="isDefault" class="rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                        Marcar como default
                    </label>
                    @error('isDefault') <p class="ml-3 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2 flex justify-end gap-2">
                    <button type="button" wire:click="cancelForm" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Cancelar
                    </button>
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Ciudad</th>
                        <th class="px-4 py-3">Timezone</th>
                        <th class="px-4 py-3">Default</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($plazas as $plaza)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $plaza->nombre }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $plaza->ciudad ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $plaza->timezone }}</td>
                            <td class="px-4 py-3">
                                @if ($plaza->is_default)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">Default</span>
                                @else
                                    <span class="text-xs text-slate-500">No</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if ($isAdmin)
                                    <div class="inline-flex items-center gap-2">
                                        @if (! $plaza->is_default)
                                            <button type="button" wire:click="markAsDefault({{ $plaza->id }})" class="rounded-md border border-emerald-300 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50">
                                                Marcar default
                                            </button>
                                        @endif
                                        <button type="button" wire:click="startEdit({{ $plaza->id }})" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                            Editar
                                        </button>
                                        <button type="button" wire:click="delete({{ $plaza->id }})" wire:confirm="¿Eliminar plaza?" class="rounded-md border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">
                                            Eliminar
                                        </button>
                                    </div>
                                @else
                                    <span class="text-xs text-slate-500">Solo lectura</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-slate-500">Sin plazas registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>

