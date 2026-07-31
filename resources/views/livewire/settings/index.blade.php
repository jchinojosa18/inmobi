<section class="space-y-6">
    <x-ui.page-header
        :title="__('settings.title')"
        :description="__('settings.description')"
    />

    @unless ($canManageSettings)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ __('settings.no_edit_permission') }}
        </div>
    @endunless

    <div class="grid gap-3 md:grid-cols-3">
        <a href="{{ route('settings.roles') }}" class="block rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm transition hover:border-slate-300 hover:shadow">
            <p class="text-sm font-semibold text-slate-900">{{ __('settings.roles_and_permissions') }}</p>
            <p class="mt-1 text-xs text-slate-600">{{ __('settings.roles_card_description') }}</p>
        </a>
        @can('invitations.manage')
            <a href="{{ route('settings.invitations.index') }}" class="block rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm transition hover:border-slate-300 hover:shadow">
                <p class="text-sm font-semibold text-slate-900">{{ __('settings.invitations') }}</p>
                <p class="mt-1 text-xs text-slate-600">{{ __('settings.invitations_card_description') }}</p>
            </a>
        @endcan
        @can('plazas.manage')
            <a href="{{ route('settings.plazas.index') }}" class="block rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm transition hover:border-slate-300 hover:shadow">
                <p class="text-sm font-semibold text-slate-900">{{ __('settings.plazas') }}</p>
                <p class="mt-1 text-xs text-slate-600">{{ __('settings.plazas_card_description') }}</p>
            </a>
        @endcan
    </div>

    <x-ui.card>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('settings.organization') }}</h2>
        <p class="mt-1 text-sm text-slate-600">{{ __('settings.organization_description') }}</p>

        <form wire:submit="saveSettings" class="mt-4 grid gap-4 md:grid-cols-3">
            <div class="md:col-span-3">
                <x-ui.input
                    :label="__('settings.organization_name')"
                    type="text"
                    wire:model.blur="organizationName"
                    :disabled="! $canManageSettings"
                />
                @error('organizationName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-3 border-t border-slate-100 pt-4">
                <h3 class="text-base font-semibold text-slate-900">{{ __('settings.receipt_folios') }}</h3>
                <p class="mt-1 text-sm text-slate-600">{{ __('settings.receipt_folios_description') }}</p>
            </div>

            <div>
                <x-ui.select :label="__('settings.folio_mode')" wire:model="receiptFolioMode" :disabled="! $canManageSettings">
                    <option value="annual">{{ __('settings.folio_mode_annual') }}</option>
                    <option value="continuous">{{ __('settings.folio_mode_continuous') }}</option>
                </x-ui.select>
                @error('receiptFolioMode') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-ui.input
                    :label="__('settings.folio_prefix')"
                    type="text"
                    wire:model.blur="receiptFolioPrefix"
                    placeholder="REC"
                    :disabled="! $canManageSettings"
                />
                @error('receiptFolioPrefix') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-ui.input
                    :label="__('settings.folio_padding')"
                    type="number"
                    min="3"
                    max="10"
                    wire:model.blur="receiptFolioPadding"
                    :disabled="! $canManageSettings"
                />
                @error('receiptFolioPadding') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-3">
                <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('settings.whatsapp_template') }}</label>
                <textarea wire:model.blur="whatsAppTemplate" rows="4" @disabled(! $canManageSettings) class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100"></textarea>
                @error('whatsAppTemplate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-3">
                <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('settings.email_template') }}</label>
                <textarea wire:model.blur="emailTemplate" rows="6" @disabled(! $canManageSettings) class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100"></textarea>
                @error('emailTemplate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-3 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                {{ __('settings.template_variables') }}
                {{ collect($templateVariables)->map(fn ($var) => '{'.$var.'}')->join(', ') }}
            </div>

            @if ($canManageSettings)
                <div class="md:col-span-3 flex justify-end">
                    <x-ui.button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="saveSettings"
                    >
                        {{ __('settings.save_settings') }}
                    </x-ui.button>
                </div>
            @endif
        </form>
    </x-ui.card>

    @if ($canManageSettings)
        <div
            x-data="{ saved: false, timer: null }"
            x-on:settings-saved.window="saved = true; clearTimeout(timer); timer = setTimeout(() => saved = false, 2500)"
            class="pointer-events-none fixed bottom-6 right-6 z-[70]"
        >
            <div
                x-show="saved"
                x-cloak
                x-transition.opacity
                class="rounded-lg bg-slate-800/95 px-4 py-2 text-sm font-medium text-white shadow-lg"
                role="status"
                aria-live="polite"
            >
                {{ __('settings.flash.configuration_saved') }}
            </div>
        </div>
    @endif

    <x-ui.card>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('settings.penalty_policy_docs') }}</h2>
        <p class="mt-1 text-sm text-slate-600">{{ __('settings.penalty_policy_docs_hint') }}</p>

        <dl class="mt-4 grid gap-4 md:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('settings.rounding') }}</dt>
                <dd class="mt-1 text-sm text-slate-800">{{ __('settings.rounding_decimals', ['scale' => $penaltyRoundingScale]) }}</dd>
            </div>
            <div class="md:col-span-2">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('settings.current_policy') }}</dt>
                <dd class="mt-1 text-sm text-slate-800">{{ $penaltyPolicy }}</dd>
            </div>
        </dl>
    </x-ui.card>

    <x-ui.card :padding="false">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('settings.expense_categories') }}</h2>
            @if ($canManageExpenseCategories)
                <form wire:submit="createExpenseCategory" class="flex flex-wrap items-center gap-2">
                    <x-ui.input
                        type="text"
                        wire:model.blur="newExpenseCategory"
                        :placeholder="__('settings.new_category_placeholder')"
                        class="w-56"
                    />
                    <x-ui.button type="submit" size="sm">
                        {{ __('settings.add') }}
                    </x-ui.button>
                </form>
            @endif
        </div>
        @error('newExpenseCategory') <p class="px-5 pt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        @error('expenseCategory') <p class="px-5 pt-2 text-sm text-red-600">{{ $message }}</p> @enderror

        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-3">{{ __('common.category') }}</th>
                <th class="px-4 py-3 text-right">{{ __('common.actions') }}</th>
            </x-slot:head>
            <x-slot:body>
                @forelse ($categories as $category)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-800">
                            @if ($editingExpenseCategoryId === $category->id)
                                <x-ui.input type="text" wire:model.blur="editingExpenseCategoryName" />
                                @error('editingExpenseCategoryName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            @else
                                <span class="inline-flex items-center gap-2">
                                    {{ $category->name }}
                                    @if ($category->is_system)
                                        <x-ui.badge variant="neutral">{{ __('settings.system_category') }}</x-ui.badge>
                                    @endif
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($canManageExpenseCategories)
                                <div class="inline-flex items-center gap-2">
                                    @if ($editingExpenseCategoryId === $category->id)
                                        <x-ui.button type="button" wire:click="updateExpenseCategory" variant="secondary" size="sm">
                                            {{ __('common.save') }}
                                        </x-ui.button>
                                        <x-ui.button type="button" wire:click="cancelEditingExpenseCategory" variant="secondary" size="sm">
                                            {{ __('common.cancel') }}
                                        </x-ui.button>
                                    @elseif (! $category->is_system)
                                        <x-ui.button type="button" wire:click="startEditingExpenseCategory({{ $category->id }})" variant="secondary" size="sm">
                                            {{ __('common.edit') }}
                                        </x-ui.button>
                                        <x-ui.button type="button" wire:click="confirmDeleteExpenseCategory({{ $category->id }})" variant="danger" size="sm">
                                            {{ __('common.delete') }}
                                        </x-ui.button>
                                    @endif
                                </div>
                            @else
                                <span class="text-xs text-slate-500">{{ __('settings.read_only') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-state :title="__('settings.empty_categories')" :colspan="2" />
                @endforelse
            </x-slot:body>
        </x-ui.table>
    </x-ui.card>

    @if ($canManageExpenseCategories)
        <x-ui.confirm-modal
            :open="$showDeleteConfirm"
            :title="__('settings.delete_category_title')"
            confirm-action="executeDeleteConfirm"
            cancel-action="cancelDeleteConfirm"
            :confirm-label="__('settings.delete_category_confirm')"
            :aria-label="__('settings.delete_category_aria')"
        >
            <p class="text-slate-700">
                {{ __('settings.delete_category_body', ['name' => $pendingDeleteCategoryName]) }}
            </p>
            <p class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                {{ __('settings.delete_category_note') }}
            </p>
        </x-ui.confirm-modal>
    @endif
</section>
