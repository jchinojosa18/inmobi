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

    @error('deposit_general')
        <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {{ $message }}
        </div>
    @enderror

    @if ($depositHolds->isNotEmpty())
        <div class="mt-4 overflow-hidden rounded-lg border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('contracts.deposit_received_at') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('common.amount') }}</th>
                        <th class="px-4 py-3">{{ __('contracts.notes') }}</th>
                        @if ($canManageCharges)
                            <th class="px-4 py-3 text-right">{{ __('common.actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @foreach ($depositHolds as $hold)
                        <tr wire:key="deposit-hold-{{ $hold->id }}">
                            <td class="px-4 py-3">{{ optional($hold->charge_date)->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-right font-medium text-slate-900">${{ number_format((float) $hold->amount, 2) }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ data_get($hold->meta, 'notes') ?: '—' }}</td>
                            @if ($canManageCharges)
                                <td class="px-4 py-3 text-right">
                                    <x-ui.button
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        wire:click="confirmVoidDeposit({{ $hold->id }})"
                                    >
                                        {{ __('contracts.void_deposit') }}
                                    </x-ui.button>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($remainingDeposit <= 0)
        <div class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-3 text-sm text-emerald-800">
            <p class="font-semibold">{{ __('contracts.deposit_complete_title') }}</p>
            <p class="mt-1">{{ __('contracts.deposit_complete_description', ['amount' => '$'.number_format($contractDepositAmount, 2)]) }}</p>
        </div>
    @else
        <form wire:submit="registerDeposit" class="mt-4 grid gap-4 md:grid-cols-3">
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

    @if ($canManageCharges)
        <x-ui.confirm-modal
            :open="$showVoidConfirm"
            :title="__('contracts.void_deposit_title')"
            confirm-action="executeVoidDeposit"
            cancel-action="cancelVoidDeposit"
            :confirm-label="__('contracts.void_deposit')"
            :cancel-label="__('common.cancel')"
            :aria-label="__('contracts.void_deposit_title')"
        >
            <p class="text-slate-700">{{ __('contracts.void_deposit_confirm') }}</p>
        </x-ui.confirm-modal>
    @endif
</x-ui.card>
