<section class="space-y-6">
    <x-ui.page-header
        title="Inquilinos"
        description="Directorio y administración de arrendatarios."
    >
        <x-slot:actions>
            @if ($canManageTenants)
                <x-ui.button type="button" wire:click="startCreate">
                    Nuevo inquilino
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="true" class="!p-4">
        <div class="grid gap-3 md:grid-cols-3">
            <div class="md:col-span-2">
                <x-ui.input
                    id="tenant-search"
                    label="Buscar"
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Nombre, email o teléfono..."
                />
            </div>

            <x-ui.select id="tenant-status-filter" label="Estado" wire:model.live="statusFilter">
                <option value="">Todos</option>
                <option value="active">Activo</option>
                <option value="inactive">Inactivo</option>
            </x-ui.select>
        </div>
    </x-ui.card>

    @if ($canManageTenants)
        <x-ui.modal
            :open="$showForm"
            :title="$editingId ? 'Editar inquilino' : 'Crear inquilino'"
            aria-label="{{ $editingId ? 'Editar inquilino' : 'Crear inquilino' }}"
            max-width="2xl"
        >
            <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nombre completo *</label>
                    <input type="text" wire:model.blur="full_name" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('full_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Correo</label>
                    <input type="email" wire:model.blur="email" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Teléfono</label>
                    <input type="text" wire:model.blur="phone" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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
            <th class="px-4 py-3">Inquilino</th>
            <th class="px-4 py-3">Estado</th>
            <th class="px-4 py-3 text-right">Contratos</th>
            <th class="px-4 py-3 text-right">Acciones</th>
        </x-slot:head>
        <x-slot:body>
            @forelse ($tenants as $tenant)
                <tr class="transition hover:bg-slate-50/80">
                    <td class="px-4 py-3">
                        <p class="font-medium text-slate-900">{{ $tenant->full_name }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $tenant->email ?: 'Sin correo' }}
                            @if ($tenant->phone)
                                · {{ $tenant->phone }}
                            @endif
                        </p>
                    </td>
                    <td class="px-4 py-3">
                        <x-ui.badge :variant="$tenant->status === 'active' ? 'success' : 'neutral'">
                            {{ $tenant->status === 'active' ? 'Activo' : 'Inactivo' }}
                        </x-ui.badge>
                    </td>
                    <td class="px-4 py-3 text-right font-medium text-slate-700">{{ $tenant->contracts_count }}</td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end">
                            @if ($canManageTenants)
                                <x-ui.button type="button" variant="secondary" size="sm" wire:click="startEdit({{ $tenant->id }})">
                                    Editar
                                </x-ui.button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <x-ui.empty-state title="No hay inquilinos con los filtros actuales." :colspan="4" />
            @endforelse
        </x-slot:body>
        <x-slot:footer>
            <div class="bg-slate-50/80 px-4 py-3">
                {{ $tenants->links() }}
            </div>
        </x-slot:footer>
    </x-ui.table>
</section>
