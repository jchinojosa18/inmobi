<section class="space-y-6">
    <x-ui.page-header
        :title="__('settings.plazas_title')"
        :description="__('settings.plazas_description')"
    >
        @if ($canManagePlazas)
            <x-slot:actions>
                <x-ui.button type="button" wire:click="startCreate">
                    {{ __('settings.new_plaza') }}
                </x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @if ($singlePlaza)
        <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            {{ __('settings.single_plaza_hint') }}
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
            :title="$editingId ? __('settings.edit_plaza') : __('settings.new_plaza')"
            :aria-label="$editingId ? __('settings.edit_plaza') : __('settings.new_plaza')"
            max-width="2xl"
        >
            <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                <div>
                    <x-ui.input :label="__('common.name')" type="text" wire:model.blur="nombre" />
                    @error('nombre') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-ui.input :label="__('settings.city')" type="text" wire:model.blur="ciudad" />
                    @error('ciudad') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-ui.input :label="__('settings.timezone')" type="text" wire:model.blur="timezone" placeholder="America/Tijuana" />
                    @error('timezone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="isDefault" class="rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                        {{ __('settings.mark_as_default') }}
                    </label>
                    @error('isDefault') <p class="ml-3 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2 flex justify-end gap-2">
                    <x-ui.button type="button" wire:click="cancelForm" variant="secondary">
                        {{ __('common.cancel') }}
                    </x-ui.button>
                    <x-ui.button type="submit">
                        {{ __('common.save') }}
                    </x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <x-ui.confirm-modal
            :open="$showDeleteConfirm"
            :title="__('settings.delete_plaza_title')"
            confirm-action="executeDeleteConfirm"
            cancel-action="cancelDeleteConfirm"
            :confirm-label="__('settings.delete_plaza_confirm')"
            :aria-label="__('settings.delete_plaza_aria')"
        >
            <p class="text-slate-700">
                {!! __('settings.delete_plaza_body', ['name' => '<span class="font-semibold text-slate-900">'.$pendingDeletePlazaName.'</span>']) !!}
            </p>
            <p class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                {{ __('settings.delete_plaza_note') }}
            </p>
        </x-ui.confirm-modal>
    @endif

    <x-ui.table>
        <x-slot:head>
            <th class="px-4 py-3">{{ __('common.name') }}</th>
            <th class="px-4 py-3">{{ __('settings.city') }}</th>
            <th class="px-4 py-3">{{ __('settings.timezone') }}</th>
            <th class="px-4 py-3">{{ __('settings.default') }}</th>
            <th class="px-4 py-3 text-right">{{ __('common.actions') }}</th>
        </x-slot:head>
        <x-slot:body>
            @forelse ($plazas as $plaza)
                <tr>
                    <td class="px-4 py-3 font-medium text-slate-900">{{ $plaza->nombre }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $plaza->ciudad ?: '—' }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $plaza->timezone }}</td>
                    <td class="px-4 py-3">
                        @if ($plaza->is_default)
                            <x-ui.badge variant="success">{{ __('settings.default') }}</x-ui.badge>
                        @else
                            <span class="text-xs text-slate-500">{{ __('settings.no') }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        @if ($canManagePlazas)
                            <div class="inline-flex items-center gap-2">
                                @if (! $plaza->is_default)
                                    <x-ui.button type="button" wire:click="markAsDefault({{ $plaza->id }})" variant="secondary" size="sm" class="border-emerald-300 text-emerald-700 hover:bg-emerald-50">
                                        {{ __('settings.mark_default') }}
                                    </x-ui.button>
                                @endif
                                <x-ui.button type="button" wire:click="startEdit({{ $plaza->id }})" variant="secondary" size="sm">
                                    {{ __('common.edit') }}
                                </x-ui.button>
                                <x-ui.button type="button" wire:click="confirmDelete({{ $plaza->id }})" variant="danger" size="sm">
                                    {{ __('common.delete') }}
                                </x-ui.button>
                            </div>
                        @else
                            <span class="text-xs text-slate-500">{{ __('settings.read_only') }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <x-ui.empty-state :title="__('settings.empty_plazas')" :colspan="5" />
            @endforelse
        </x-slot:body>
    </x-ui.table>
</section>
