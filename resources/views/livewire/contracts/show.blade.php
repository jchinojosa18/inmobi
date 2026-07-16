<section class="space-y-6">
    <x-ui.page-header
        :title="__('contracts.show_title', ['id' => $contract->id])"
        :description="$contract->tenant->full_name.' · '.$contract->unit->property->name.' / '.$contract->unit->name"
    >
        <x-slot:actions>
            @if ($canCreatePayments)
                <x-ui.button
                    type="button"
                    variant="accent"
                    onclick="Livewire.dispatch('open-quick-payment', { contractId: {{ $contract->id }} })"
                >
                    {{ __('common.register_payment') }}
                </x-ui.button>
            @endif
            @if ($canManageContracts)
                <x-ui.button
                    type="button"
                    onclick="Livewire.dispatch('open-contract-edit', { contractId: {{ $contract->id }} })"
                >
                    {{ __('contracts.edit_contract') }}
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 md:grid-cols-4">
        <x-ui.stat-card
            :label="__('contracts.status')"
            :value="$contract->status === 'active' ? __('common.active') : __('common.finished')"
        />
        <x-ui.stat-card :label="__('contracts.accumulated_charges')" :value="'$'.number_format($chargesTotal, 2)" />
        <x-ui.stat-card :label="__('contracts.applied')" :value="'$'.number_format($allocatedTotal, 2)" />
        <x-ui.stat-card :label="__('contracts.credit_balance_short')" :value="'$'.number_format($creditTotal, 2)" />
    </div>

    @if ($canManageCharges)
        <livewire:contracts.deposit-hold-form
            :contract="$contract"
            :key="'deposit-hold-'.$contract->id"
        />
    @endif

    @if ($canSettleContracts)
        <livewire:contracts.settlement-wizard
            :contract="$contract"
            :key="'settlement-wizard-'.$contract->id"
        />
    @endif

    @if ($canManageCharges)
        <x-ui.card>
            <h2 class="text-lg font-semibold text-slate-900">{{ __('contracts.create_adjustment') }}</h2>
            <p class="mt-1 text-sm text-slate-600">
                {{ __('contracts.adjustment_description') }}
            </p>

            <form wire:submit="createAdjustment" class="mt-4 grid gap-4 md:grid-cols-2">
                @error('adjustment_month_close')
                    <div class="md:col-span-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                        {{ $message }}
                    </div>
                @enderror

                <div>
                    <x-ui.input :label="__('contracts.adjustment_amount').' *'" type="number" step="0.01" wire:model.blur="adjustment_amount" />
                    @error('adjustment_amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-ui.input :label="__('common.date').' *'" type="date" wire:model.blur="adjustment_charge_date" />
                    @error('adjustment_charge_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-ui.input :label="__('contracts.adjustment_reason').' *'" type="text" wire:model.blur="adjustment_reason" />
                    @error('adjustment_reason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-ui.input :label="__('contracts.adjustment_linked_to')" type="text" wire:model.blur="adjustment_linked_to" :placeholder="__('contracts.adjustment_linked_placeholder')" />
                    @error('adjustment_linked_to') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('contracts.comment') }}</label>
                    <textarea wire:model.blur="adjustment_comment" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea>
                    @error('adjustment_comment') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2 flex justify-end">
                    <x-ui.button type="submit">
                        {{ __('contracts.register_adjustment') }}
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('contracts.account_statement') }}</h2>
            <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('contracts.pending_balance') }}</p>
                <p class="font-semibold text-slate-900">${{ number_format($pendingBalance, 2) }}</p>
            </div>
        </div>

        <div class="mt-4 space-y-4">
            @forelse ($ledgerGroups as $group)
                <div class="overflow-hidden rounded-lg border border-slate-200">
                    <div class="grid gap-2 bg-slate-50 px-4 py-3 text-sm sm:grid-cols-4">
                        <p class="font-semibold text-slate-900">{{ __('contracts.period_label', ['period' => $group['period_label']]) }}</p>
                        <p class="text-slate-600">{{ __('contracts.charges') }}: <span class="font-medium text-slate-900">${{ number_format($group['charges_total'], 2) }}</span></p>
                        <p class="text-slate-600">{{ __('contracts.paid') }}: <span class="font-medium text-slate-900">${{ number_format($group['paid_total'], 2) }}</span></p>
                        <p class="text-slate-600">{{ __('common.balance') }}: <span class="font-medium text-slate-900">${{ number_format($group['balance_total'], 2) }}</span></p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-white text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">{{ __('contracts.period') }}</th>
                                    <th class="px-4 py-3">{{ __('contracts.charge_type') }}</th>
                                    <th class="px-4 py-3">{{ __('contracts.charge_date') }}</th>
                                    <th class="px-4 py-3">{{ __('contracts.due') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('common.amount') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('contracts.paid') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('common.balance') }}</th>
                                    <th class="px-4 py-3">{{ __('contracts.charge_status') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @foreach ($group['rows'] as $row)
                                    <tr>
                                        <td class="px-4 py-3">{{ $row['period_label'] }}</td>
                                        <td class="px-4 py-3 font-medium text-slate-900">{{ $row['type'] }}</td>
                                        <td class="px-4 py-3">{{ $row['charge_date'] ?: '-' }}</td>
                                        <td class="px-4 py-3">{{ $row['due_date'] }}</td>
                                        <td class="px-4 py-3 text-right">${{ number_format($row['amount'], 2) }}</td>
                                        <td class="px-4 py-3 text-right">${{ number_format($row['paid'], 2) }}</td>
                                        <td class="px-4 py-3 text-right font-medium text-slate-900">${{ number_format($row['balance'], 2) }}</td>
                                        <td class="px-4 py-3">
                                            @php
                                                $statusVariant = match ($row['status_tone']) {
                                                    'red' => 'danger',
                                                    'amber' => 'warning',
                                                    'emerald' => 'success',
                                                    'blue' => 'info',
                                                    default => 'neutral',
                                                };
                                            @endphp
                                            <x-ui.badge :variant="$statusVariant">
                                                {{ $row['status_label'] }}
                                            </x-ui.badge>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <p class="rounded-md border border-slate-200 bg-slate-50 px-4 py-6 text-center text-slate-500">
                    {{ __('contracts.no_charges_for_contract') }}
                </p>
            @endforelse
        </div>
    </x-ui.card>

    <x-ui.card :padding="false">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('contracts.recent_payments') }}</h2>
        </div>

        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-3">{{ __('common.folio') }}</th>
                <th class="px-4 py-3">{{ __('common.date') }}</th>
                <th class="px-4 py-3">{{ __('contracts.method') }}</th>
                <th class="px-4 py-3 text-right">{{ __('common.amount') }}</th>
                <th class="px-4 py-3 text-right">{{ __('contracts.applied') }}</th>
                <th class="px-4 py-3 text-right">{{ __('common.actions') }}</th>
            </x-slot:head>
            <x-slot:body>
                @forelse ($payments as $payment)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $payment['folio'] ?? __('common.n_a') }}</td>
                        <td class="px-4 py-3">{{ optional($payment['paid_at'])->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3">{{ $payment['method'] }}</td>
                        <td class="px-4 py-3 text-right">${{ number_format($payment['amount'], 2) }}</td>
                        <td class="px-4 py-3 text-right">${{ number_format($payment['allocated_amount'], 2) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                @if ($canViewPayments)
                                    <x-ui.button href="{{ $payment['show_url'] }}" variant="secondary" size="sm">
                                        {{ __('common.view_payment') }}
                                    </x-ui.button>
                                    @if ($payment['folio'] !== null)
                                        <x-ui.button href="{{ $payment['receipt_url'] }}" variant="secondary" size="sm" target="_blank" rel="noopener noreferrer">
                                            {{ __('common.receipt_pdf') }}
                                        </x-ui.button>
                                        <x-ui.button href="{{ $payment['share_url'] }}" size="sm" target="_blank" rel="noopener noreferrer">
                                            {{ __('contracts.shareable_link') }}
                                        </x-ui.button>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-state :title="__('contracts.no_payments_yet')" :colspan="6" />
                @endforelse
            </x-slot:body>
        </x-ui.table>
    </x-ui.card>

    <livewire:documents.panel
        :documentable-type="\App\Models\Contract::class"
        :documentable-id="$contract->id"
        :title="__('contracts.contract_documents')"
        :key="'contract-documents-'.$contract->id"
    />
</section>
