<div>
    @if($open)
    <div
        id="quick-expense-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="quick-expense-modal-title"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        @include('components.ui.partials.modal-focus-trap')
    >
        <div
            class="absolute inset-0 bg-black/50"
            wire:click="close"
            aria-hidden="true"
        ></div>

        <div
            data-modal-panel
            tabindex="-1"
            class="relative z-10 w-full max-w-xl rounded-2xl bg-white shadow-xl outline-none"
        >
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <h2 id="quick-expense-modal-title" class="text-base font-semibold text-slate-900">{{ __('finance.expenses.register_modal') }}</h2>
                <button
                    type="button"
                    wire:click="close"
                    class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                    aria-label="{{ __('common.close') }}"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="max-h-[80vh] overflow-y-auto px-5 py-4">
                <div class="space-y-4">
                    @error('month_close')
                        <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                            {{ $message }}
                        </div>
                    @enderror

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <x-ui.input
                                id="qem-spent-at"
                                :label="__('common.date').' *'"
                                type="date"
                                wire:model.blur="spentAt"
                            />
                            @error('spentAt') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <x-ui.input
                                id="qem-amount"
                                :label="__('common.amount').' *'"
                                type="number"
                                step="0.01"
                                min="0.01"
                                wire:model.blur="amount"
                                placeholder="0.00"
                            />
                            @error('amount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <x-ui.input
                            id="qem-category"
                            :label="__('common.category').' *'"
                            type="text"
                            list="qem-categories-list"
                            wire:model.blur="category"
                            :placeholder="__('finance.expenses.category_placeholder')"
                            autocomplete="off"
                        />
                        <datalist id="qem-categories-list">
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}"></option>
                            @endforeach
                        </datalist>
                        @error('category') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-medium text-slate-700">{{ __('finance.expenses.assignment') }}</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                                <input type="radio" wire:model.live="scope" value="general" class="accent-slate-700">
                                {{ __('finance.expenses.general_expense') }}
                            </label>
                            <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                                <input type="radio" wire:model.live="scope" value="unit" class="accent-slate-700">
                                {{ __('finance.expenses.assign_to_unit') }}
                            </label>
                        </div>
                    </div>

                    @if($scope === 'unit')
                    <div x-data>
                        <label class="mb-1 block text-xs font-medium text-slate-700">{{ __('common.unit') }} *</label>
                        <div class="relative">
                            <input
                                id="qem-unit-input"
                                type="text"
                                wire:model.live.debounce.200ms="unitQuery"
                                wire:keydown.escape="$set('unitResults', [])"
                                :placeholder="__('finance.expenses.unit_search_placeholder')"
                                autocomplete="off"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
                            >

                            @if(count($unitResults) > 0)
                            <ul class="absolute left-0 right-0 top-full z-20 mt-1 max-h-48 overflow-y-auto divide-y divide-slate-100 rounded-md border border-slate-200 bg-white shadow-md">
                                @foreach($unitResults as $result)
                                <li>
                                    <button
                                        type="button"
                                        wire:click="selectUnit({{ $result['id'] }})"
                                        class="w-full px-3 py-2 text-left text-sm text-slate-800 hover:bg-slate-50"
                                    >
                                        {{ $result['label'] }}
                                    </button>
                                </li>
                                @endforeach
                            </ul>
                            @endif
                        </div>

                        @if($unitId)
                            <p class="mt-1 text-xs text-emerald-700">
                                {{ __('finance.expenses.unit_selected', ['id' => $unitId]) }}
                            </p>
                        @endif
                        @error('unitId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    @endif

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <x-ui.input
                                id="qem-vendor"
                                :label="__('common.vendor').' ('.__('common.optional').')'"
                                type="text"
                                wire:model.blur="vendor"
                                maxlength="150"
                                :placeholder="__('finance.expenses.vendor_placeholder')"
                            />
                            @error('vendor') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <x-ui.input
                                id="qem-notes"
                                :label="__('common.notes').' ('.__('common.optional').')'"
                                type="text"
                                wire:model.blur="notes"
                                maxlength="1000"
                                :placeholder="__('finance.expenses.notes_placeholder')"
                            />
                            @error('notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <x-ui.file-input
                            id="qem-evidence"
                            wire:model="evidenceFile"
                            accept=".jpg,.jpeg,.png,.pdf"
                            reset-event="expense-evidence-reset"
                            loading-target="evidenceFile"
                        >
                            <x-slot:labelSlot>
                                {{ __('finance.payments.evidence_label') }} <span class="text-slate-400">{{ __('finance.expenses.evidence_hint') }}</span>
                            </x-slot:labelSlot>
                        </x-ui.file-input>
                        @error('evidenceFile') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-5 py-4">
                <x-ui.button type="button" variant="secondary" wire:click="close">
                    {{ __('common.cancel') }}
                </x-ui.button>
                <x-ui.button
                    type="button"
                    wire:click="save"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-60 cursor-not-allowed"
                >
                    <span wire:loading.remove wire:target="save">{{ __('finance.expenses.save_expense') }}</span>
                    <span wire:loading wire:target="save">{{ __('common.saving') }}</span>
                </x-ui.button>
            </div>
        </div>
    </div>
    @endif
</div>
