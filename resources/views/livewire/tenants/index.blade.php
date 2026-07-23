<section class="space-y-6">
    <x-ui.page-header
        :title="__('catalog.tenants.title')"
        :description="__('catalog.tenants.description')"
    >
        <x-slot:actions>
            @if ($canManageTenants)
                <x-ui.button type="button" wire:click="startCreate">
                    {{ __('catalog.tenants.new_tenant') }}
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="true" class="!p-4">
        <div class="grid gap-3 md:grid-cols-3">
            <div class="md:col-span-2">
                <x-ui.input
                    id="tenant-search"
                    :label="__('common.search')"
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    :placeholder="__('catalog.tenants.search_placeholder')"
                />
            </div>

            <x-ui.select id="tenant-status-filter" :label="__('common.status')" wire:model.live="statusFilter">
                <option value="">{{ __('common.all') }}</option>
                <option value="active">{{ __('common.active') }}</option>
                <option value="inactive">{{ __('common.inactive') }}</option>
            </x-ui.select>
        </div>
    </x-ui.card>

    @if ($canManageTenants)
        <x-ui.modal
            :open="$showForm"
            :title="$editingId ? __('catalog.tenants.edit_tenant') : __('catalog.tenants.create_tenant')"
            :aria-label="$editingId ? __('catalog.tenants.edit_tenant') : __('catalog.tenants.create_tenant')"
            max-width="2xl"
        >
            <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('common.full_name') }} *</label>
                    <input type="text" wire:model.blur="full_name" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('full_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('common.email') }}</label>
                    <input type="email" wire:model.blur="email" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('common.phone') }}</label>
                    <input type="text" wire:model.blur="phone" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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
            <th class="px-4 py-3">{{ __('common.tenant') }}</th>
            <th class="px-4 py-3">{{ __('common.status') }}</th>
            <th class="px-4 py-3 text-right">{{ __('common.contracts') }}</th>
            <th class="px-4 py-3 text-right">{{ __('common.actions') }}</th>
        </x-slot:head>
        <x-slot:body>
            @forelse ($tenants as $tenant)
                <tr wire:key="tenant-row-{{ $tenant->id }}" class="transition hover:bg-slate-50/80">
                    <td class="px-4 py-3">
                        <a href="{{ route('tenants.show', $tenant) }}" class="font-medium text-indigo-600 hover:text-indigo-500 hover:underline">
                            {{ $tenant->full_name }}
                        </a>
                        <p class="text-xs text-slate-500">
                            {{ $tenant->email ?: __('common.no_email') }}
                            @if ($tenant->phone)
                                · {{ $tenant->phone }}
                            @endif
                        </p>
                    </td>
                    <td class="px-4 py-3">
                        <x-ui.badge :variant="$tenant->status === 'active' ? 'success' : 'neutral'">
                            {{ $tenant->status === 'active' ? __('common.active') : __('common.inactive') }}
                        </x-ui.badge>
                    </td>
                    <td class="px-4 py-3 text-right font-medium text-slate-700">{{ $tenant->contracts_count }}</td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end">
                            @if ($canManageTenants)
                                <x-ui.button type="button" variant="secondary" size="sm" wire:click="startEdit({{ $tenant->id }})">
                                    {{ __('common.edit') }}
                                </x-ui.button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <x-ui.empty-state :title="__('catalog.tenants.empty')" :colspan="4" />
            @endforelse
        </x-slot:body>
        <x-slot:footer>
            <div class="bg-slate-50/80 px-4 py-3">
                {{ $tenants->links() }}
            </div>
        </x-slot:footer>
    </x-ui.table>
</section>
