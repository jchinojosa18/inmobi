<section class="space-y-6">
    <x-ui.page-header
        :title="__('finance.payments.title')"
        :description="'#'. $contract->id .' · '. $contract->tenant->full_name .' · '. $contract->unit->name"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('contracts.show', $contract) }}" variant="secondary">
                {{ __('common.back_to_contract') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card>
        <form wire:submit="save" class="grid gap-4 md:grid-cols-2" enctype="multipart/form-data">
            @error('month_close')
                <div class="md:col-span-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ $message }}
                </div>
            @enderror

            <div>
                <x-ui.input
                    id="payment-amount"
                    :label="__('common.amount').' *'"
                    type="number"
                    step="0.01"
                    min="0.01"
                    wire:model.blur="amount"
                />
                @error('amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-ui.select id="payment-method" :label="__('common.method').' *'" wire:model="method">
                    <option value="TRANSFER">{{ __('finance.payments.methods.TRANSFER') }}</option>
                    <option value="CASH">{{ __('finance.payments.methods.CASH') }}</option>
                </x-ui.select>
                @error('method') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-ui.input
                    id="payment-paid-at"
                    :label="__('finance.payments.paid_at').' *'"
                    type="datetime-local"
                    wire:model.blur="paid_at"
                />
                @error('paid_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-ui.input
                    id="payment-reference"
                    :label="__('finance.payments.reference')"
                    type="text"
                    wire:model.blur="reference"
                />
                @error('reference') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <x-ui.input
                    id="payment-evidence"
                    :label="__('finance.payments.evidence_optional')"
                    type="file"
                    wire:model="evidence"
                    accept=".jpg,.jpeg,.png,.pdf"
                />
                @error('evidence') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-slate-500">{{ __('finance.payments.evidence_types') }}</p>
            </div>

            <div class="md:col-span-2 flex justify-end">
                <x-ui.button
                    type="submit"
                    wire:loading.attr="disabled"
                >
                    {{ __('finance.payments.save_payment') }}
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</section>
