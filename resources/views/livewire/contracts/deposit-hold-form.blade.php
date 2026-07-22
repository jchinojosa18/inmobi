<x-ui.card>
    <h2 class="text-lg font-semibold text-slate-900">{{ __('contracts.deposit_received') }}</h2>
    <p class="mt-1 text-sm text-slate-600">
        {!! __('contracts.deposit_received_description', ['code' => '<code>DEPOSIT_HOLD</code>']) !!}
    </p>

    <div class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('contracts.deposit_contract_amount') }}</p>
            <p class="font-semibold text-slate-900">${{ number_format($contractDepositAmount, 2) }}</p>
        </div>
        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('contracts.deposit_registered') }}</p>
            <p class="font-semibold text-slate-900">${{ number_format($registeredDeposit, 2) }}</p>
        </div>
        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('contracts.deposit_remaining') }}</p>
            <p class="font-semibold text-slate-900">${{ number_format($remainingDeposit, 2) }}</p>
        </div>
    </div>

    @if ($remainingDeposit <= 0)
        <div class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-3 text-sm text-emerald-800">
            <p class="font-semibold">{{ __('contracts.deposit_complete_title') }}</p>
            <p class="mt-1">{{ __('contracts.deposit_complete_description', ['amount' => '$'.number_format($contractDepositAmount, 2)]) }}</p>
        </div>
    @else
        <form wire:submit="registerDeposit" class="mt-4 grid gap-4 md:grid-cols-3">
            @error('deposit_general')
                <div class="md:col-span-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ $message }}
                </div>
            @enderror

            <div>
                <x-ui.input :label="__('contracts.deposit_received_at').' *'" type="date" wire:model.blur="deposit_received_at" />
                @error('deposit_received_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-ui.input :label="__('common.amount').' *'" type="number" step="0.01" min="0.01" wire:model.blur="deposit_amount" />
                @error('deposit_amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-ui.input :label="__('contracts.notes')" type="text" wire:model.blur="deposit_notes" />
                @error('deposit_notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-3 flex justify-end">
                <x-ui.button type="submit">
                    {{ __('contracts.register_deposit') }}
                </x-ui.button>
            </div>
        </form>
    @endif
</x-ui.card>
