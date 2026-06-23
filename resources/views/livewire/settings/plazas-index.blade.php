<section class="space-y-6">
    <x-ui.page-header
        title="Plazas"
        description="Administración de plazas por organización (multi-ciudad)."
    >
        @if ($canManagePlazas)
            <x-slot:actions>
                <x-ui.button type="button" wire:click="startCreate">
                    Nueva plaza
                </x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

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

    @if ($canManagePlazas)
        <x-ui.modal
            :open="$showForm"
            :title="$editingId ? 'Editar plaza' : 'Nueva plaza'"
            aria-label="{{ $editingId ? 'Editar plaza' : 'Nueva plaza' }}"
            max-width="2xl"
        >
            <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                <div>
                    <x-ui.input label="Nombre" type="text" wire:model.blur="nombre" />
                    @error('nombre') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-ui.input label="Ciudad" type="text" wire:model.blur="ciudad" />
                    @error('ciudad') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-ui.input label="Timezone" type="text" wire:model.blur="timezone" placeholder="America/Tijuana" />
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
                    <x-ui.button type="button" wire:click="cancelForm" variant="secondary">
                        Cancelar
                    </x-ui.button>
                    <x-ui.button type="submit">
                        Guardar
                    </x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <x-ui.confirm-modal
            :open="$showDeleteConfirm"
            title="Eliminar plaza"
            confirm-action="executeDeleteConfirm"
            cancel-action="cancelDeleteConfirm"
            confirm-label="Eliminar plaza"
            aria-label="Confirmar eliminación de plaza"
        >
            <p class="text-slate-700">
                Vas a eliminar la plaza <span class="font-semibold text-slate-900">{{ $pendingDeletePlazaName }}</span>.
            </p>
            <p class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                Las propiedades asociadas conservarán su plaza asignada hasta que la modifiques manualmente.
            </p>
        </x-ui.confirm-modal>
    @endif

    <x-ui.table>
        <x-slot:head>
            <th class="px-4 py-3">Nombre</th>
            <th class="px-4 py-3">Ciudad</th>
            <th class="px-4 py-3">Timezone</th>
            <th class="px-4 py-3">Default</th>
            <th class="px-4 py-3 text-right">Acciones</th>
        </x-slot:head>
        <x-slot:body>
            @forelse ($plazas as $plaza)
                <tr>
                    <td class="px-4 py-3 font-medium text-slate-900">{{ $plaza->nombre }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $plaza->ciudad ?: '—' }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $plaza->timezone }}</td>
                    <td class="px-4 py-3">
                        @if ($plaza->is_default)
                            <x-ui.badge variant="success">Default</x-ui.badge>
                        @else
                            <span class="text-xs text-slate-500">No</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        @if ($canManagePlazas)
                            <div class="inline-flex items-center gap-2">
                                @if (! $plaza->is_default)
                                    <x-ui.button type="button" wire:click="markAsDefault({{ $plaza->id }})" variant="secondary" size="sm" class="border-emerald-300 text-emerald-700 hover:bg-emerald-50">
                                        Marcar default
                                    </x-ui.button>
                                @endif
                                <x-ui.button type="button" wire:click="startEdit({{ $plaza->id }})" variant="secondary" size="sm">
                                    Editar
                                </x-ui.button>
                                <x-ui.button type="button" wire:click="confirmDelete({{ $plaza->id }})" variant="danger" size="sm">
                                    Eliminar
                                </x-ui.button>
                            </div>
                        @else
                            <span class="text-xs text-slate-500">Solo lectura</span>
                        @endif
                    </td>
                </tr>
            @empty
                <x-ui.empty-state title="Sin plazas registradas." :colspan="5" />
            @endforelse
        </x-slot:body>
    </x-ui.table>
</section>
