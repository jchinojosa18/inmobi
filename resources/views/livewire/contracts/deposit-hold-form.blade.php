<x-ui.card>
    <div
        x-data="{ open: {{ $remainingDeposit > 0 ? 'true' : 'false' }} }"
        class="space-y-0"
    >
        <button
            type="button"
            class="flex w-full items-center justify-between gap-3 text-left"
            @click="open = !open"
            :aria-expanded="open.toString()"
            aria-controls="deposit-hold-panel"
            aria-expanded="{{ $remainingDeposit > 0 ? 'true' : 'false' }}"
            aria-label="{{ __('contracts.deposit_panel_toggle') }}"
        >
            <div class="min-w-0 flex-1">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('contracts.deposit_received') }}</h2>
                @if ($remainingDeposit > 0)
                    <p class="mt-0.5 text-sm text-slate-600">
                        ${{ number_format($registeredDeposit, 2) }} / ${{ number_format($remainingDeposit, 2) }}
                    </p>
                @else
                    <p class="mt-0.5 text-sm font-medium text-emerald-700">
                        {{ __('contracts.deposit_complete_title') }}
                    </p>
                @endif
            </div>
            <svg
                class="h-5 w-5 shrink-0 text-slate-500 transition-transform"
                :class="{ 'rotate-180': open }"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
        </button>

        <div
            id="deposit-hold-panel"
            x-show="open"
            x-cloak
            class="mt-4"
        >
            <p class="text-sm text-slate-600">
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
                                <th class="px-4 py-3">{{ __('common.folio') }}</th>
                                <th class="px-4 py-3">{{ __('contracts.notes') }}</th>
                                @if ($canManageCharges || $canViewDocuments)
                                    <th class="px-4 py-3 text-right">{{ __('common.actions') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($depositHolds as $hold)
                                <tr wire:key="deposit-hold-{{ $hold['id'] }}">
                                    <td class="px-4 py-3">{{ $hold['charge_date'] ?: '—' }}</td>
                                    <td class="px-4 py-3 text-right font-medium text-slate-900">${{ number_format($hold['amount'], 2) }}</td>
                                    <td class="px-4 py-3">{{ $hold['receipt_folio'] ?: '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $hold['notes'] ?: '—' }}</td>
                                    @if ($canManageCharges || $canViewDocuments)
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap justify-end gap-2">
                                                @if ($hold['receipt_url'] && $canManageCharges)
                                                    <x-ui.button
                                                        href="{{ $hold['receipt_url'] }}"
                                                        variant="secondary"
                                                        size="sm"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                    >
                                                        {{ __('contracts.deposit_receipt_pdf') }}
                                                    </x-ui.button>
                                                @endif
                                                @if ($canViewDocuments)
                                                    <x-ui.button
                                                        type="button"
                                                        variant="secondary"
                                                        size="sm"
                                                        wire:click="toggleEvidence({{ $hold['id'] }})"
                                                    >
                                                        {{ $evidenceChargeId === $hold['id'] ? __('contracts.hide_deposit_evidence') : __('contracts.deposit_evidence') }}
                                                    </x-ui.button>
                                                @endif
                                                @if ($canManageCharges)
                                                    <x-ui.button
                                                        type="button"
                                                        variant="secondary"
                                                        size="sm"
                                                        wire:click="confirmVoidDeposit({{ $hold['id'] }})"
                                                    >
                                                        {{ __('contracts.void_deposit') }}
                                                    </x-ui.button>
                                                @endif
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                                @if ($canViewDocuments && $evidenceChargeId === $hold['id'])
                                    <tr wire:key="deposit-hold-evidence-{{ $hold['id'] }}">
                                        <td colspan="5" class="bg-slate-50 px-4 py-4">
                                            <livewire:documents.panel
                                                :documentable-type="\App\Models\Charge::class"
                                                :documentable-id="$hold['id']"
                                                :title="__('contracts.deposit_evidence')"
                                                :key="'deposit-docs-'.$hold['id']"
                                            />
                                        </td>
                                    </tr>
                                @endif
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
                <form wire:submit="registerDeposit" class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <x-ui.input :label="__('contracts.deposit_received_at').' *'" type="date" wire:model.blur="deposit_received_at" />
                        @error('deposit_received_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <x-ui.input :label="__('common.amount').' *'" type="number" step="0.01" min="0.01" wire:model.blur="deposit_amount" />
                        @error('deposit_amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('common.method') }} *</label>
                        <select wire:model.blur="deposit_method" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <option value="{{ \App\Models\Payment::METHOD_TRANSFER }}">{{ __('finance.payments.methods.TRANSFER') }}</option>
                            <option value="{{ \App\Models\Payment::METHOD_CASH }}">{{ __('finance.payments.methods.CASH') }}</option>
                        </select>
                        @error('deposit_method') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <x-ui.input :label="__('contracts.notes')" type="text" wire:model.blur="deposit_notes" />
                        @error('deposit_notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2 lg:col-span-4 flex justify-end">
                        <x-ui.button type="submit">
                            {{ __('contracts.register_deposit') }}
                        </x-ui.button>
                    </div>
                </form>
            @endif
        </div>
    </div>

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
