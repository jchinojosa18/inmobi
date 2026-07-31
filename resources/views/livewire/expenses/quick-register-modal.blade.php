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
                        <x-ui.select id="qem-category" :label="__('common.category').' *'" wire:model.blur="expenseCategoryId">
                            <option value="">{{ __('common.select') }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </x-ui.select>
                        @error('expenseCategoryId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <x-ui.select id="qem-unit" :label="__('finance.expenses.assignment')" wire:model.live="unitId">
                            <option value="">{{ __('finance.expenses.general_expense') }}</option>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}">
                                    {{ trim($unit->property_name.' / '.$unit->name.($unit->code ? " ({$unit->code})" : '')) }}
                                </option>
                            @endforeach
                        </x-ui.select>
                        @error('unitId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    @if ($unitId)
                        <div>
                            <x-ui.select id="qem-contract" :label="__('common.contract')" wire:model.blur="contractId">
                                <option value="">{{ __('common.none') }}</option>
                                @foreach ($contracts as $contract)
                                    <option value="{{ $contract->id }}">
                                        {{ $contract->tenant?->full_name ?? __('finance.cash_flow.no_tenant') }}
                                        — {{ $contract->status === 'active' ? __('contracts.status_active') : __('contracts.status_ended') }}
                                    </option>
                                @endforeach
                            </x-ui.select>
                            @error('contractId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <x-ui.input
                                id="qem-vendor"
                                :label="__('common.vendor')"
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
                                :label="__('common.notes')"
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
