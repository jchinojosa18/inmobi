<section class="space-y-6">
    @php
        $pendingBalance = $summary->pendingBalance();
        $activeContractsCount = $summary->activeContractsCount();
        $creditBalance = $summary->creditBalance();
        $headerDescription = collect([
            $tenant->email ?: __('common.no_email'),
            $tenant->phone,
        ])->filter()->implode(' · ');
    @endphp

    <x-ui.page-header :title="$tenant->full_name" :description="$headerDescription">
        <x-slot:actions>
            <x-ui.button href="{{ route('tenants.index') }}" variant="secondary">
                {{ __('catalog.tenants.kardex.back_to_tenants') }}
            </x-ui.button>
            @if ($canManageTenants)
                <x-ui.button type="button" wire:click="startEdit">
                    {{ __('common.edit') }}
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-ui.stat-card
            :label="__('catalog.tenants.kardex.active_contracts')"
            :value="$activeContractsCount"
        />
        <x-ui.stat-card
            :label="__('catalog.tenants.kardex.pending_balance')"
            :value="'$'.number_format($pendingBalance, 2)"
            :tone="$pendingBalance > 0 ? 'danger' : 'default'"
            :valueClass="$pendingBalance > 0 ? 'text-rose-700' : null"
        />
        <x-ui.stat-card
            :label="__('catalog.tenants.kardex.credit_balance')"
            :value="'$'.number_format($creditBalance, 2)"
        />
    </div>

    <x-ui.card>
        <h2 class="text-sm font-semibold text-slate-900">{{ __('catalog.tenants.kardex.profile_title') }}</h2>
        <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('common.full_name') }}</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $tenant->full_name }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('common.email') }}</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $tenant->email ?: __('common.no_email') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('common.phone') }}</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $tenant->phone ?: __('common.n_a') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('catalog.tenants.ine_clave') }}</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $tenant->ine_clave ?: __('common.n_a') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('common.status') }}</dt>
                <dd class="mt-1">
                    <x-ui.badge :variant="$tenant->status === 'active' ? 'success' : 'neutral'">
                        {{ $tenant->status === 'active' ? __('common.active') : __('common.inactive') }}
                    </x-ui.badge>
                </dd>
            </div>
            @if ($tenant->notes)
                <div class="sm:col-span-2 lg:col-span-4">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('common.notes') }}</dt>
                    <dd class="mt-1 text-sm text-slate-600">{{ $tenant->notes }}</dd>
                </div>
            @endif
        </dl>
    </x-ui.card>

    <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
        <div class="border-b border-slate-200/80 px-2 sm:px-4" role="tablist" aria-label="{{ __('catalog.tenants.kardex.page_title') }}">
            <div class="flex gap-1 overflow-x-auto">
                <button
                    type="button"
                    role="tab"
                    wire:click="setTab('contracts')"
                    aria-selected="{{ $tab === 'contracts' ? 'true' : 'false' }}"
                    @class([
                        'relative shrink-0 border-b-2 px-3 py-3 text-sm sm:px-4',
                        'border-indigo-600 font-semibold text-indigo-700' => $tab === 'contracts',
                        'border-transparent font-medium text-slate-500 hover:text-slate-700' => $tab !== 'contracts',
                    ])
                >
                    {{ __('catalog.tenants.kardex.tab_contracts') }}
                    <span @class([
                        'ml-1.5 inline-flex min-w-[1.25rem] items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-medium',
                        'bg-indigo-50 text-indigo-700' => $tab === 'contracts',
                        'bg-slate-100 text-slate-600' => $tab !== 'contracts',
                    ])>{{ $contracts->count() }}</span>
                </button>
                <button
                    type="button"
                    role="tab"
                    wire:click="setTab('charges')"
                    aria-selected="{{ $tab === 'charges' ? 'true' : 'false' }}"
                    @class([
                        'relative shrink-0 border-b-2 px-3 py-3 text-sm sm:px-4',
                        'border-indigo-600 font-semibold text-indigo-700' => $tab === 'charges',
                        'border-transparent font-medium text-slate-500 hover:text-slate-700' => $tab !== 'charges',
                    ])
                >
                    {{ __('catalog.tenants.kardex.tab_charges') }}
                    <span @class([
                        'ml-1.5 inline-flex min-w-[1.25rem] items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-medium',
                        'bg-rose-50 text-rose-700' => $tab === 'charges',
                        'bg-slate-100 text-slate-600' => $tab !== 'charges',
                    ])>{{ $charges->count() }}</span>
                </button>
                <button
                    type="button"
                    role="tab"
                    wire:click="setTab('payments')"
                    aria-selected="{{ $tab === 'payments' ? 'true' : 'false' }}"
                    @class([
                        'relative shrink-0 border-b-2 px-3 py-3 text-sm sm:px-4',
                        'border-indigo-600 font-semibold text-indigo-700' => $tab === 'payments',
                        'border-transparent font-medium text-slate-500 hover:text-slate-700' => $tab !== 'payments',
                    ])
                >
                    {{ __('catalog.tenants.kardex.tab_payments') }}
                    <span @class([
                        'ml-1.5 inline-flex min-w-[1.25rem] items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-medium',
                        'bg-indigo-50 text-indigo-700' => $tab === 'payments',
                        'bg-slate-100 text-slate-600' => $tab !== 'payments',
                    ])>{{ $payments->count() }}</span>
                </button>
            </div>
        </div>

        @if ($tab === 'contracts')
            <x-ui.table :flush="true">
                <x-slot:head>
                    <th class="px-4 py-3">{{ __('common.unit') }}</th>
                    <th class="px-4 py-3">{{ __('common.status') }}</th>
                    <th class="px-4 py-3">{{ __('contracts.start_date') }}</th>
                    <th class="px-4 py-3">{{ __('contracts.end_date') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('contracts.monthly_rent') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('common.actions') }}</th>
                </x-slot:head>
                <x-slot:body>
                    @forelse ($contracts as $contract)
                        <tr wire:key="kardex-contract-{{ $contract['id'] }}" class="transition hover:bg-slate-50/80">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ $contract['unit_label'] }}</p>
                                <p class="text-xs text-slate-500">#{{ $contract['id'] }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <x-ui.badge :variant="$contract['status'] === 'active' ? 'success' : 'neutral'">
                                    {{ $contract['status'] === 'active' ? __('common.active') : __('common.finished') }}
                                </x-ui.badge>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $contract['starts_at'] ?: __('common.n_a') }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $contract['ends_at'] ?: __('common.n_a') }}</td>
                            <td class="px-4 py-3 text-right font-medium text-slate-900">${{ number_format($contract['rent_amount'], 2) }}</td>
                            <td class="px-4 py-3 text-right">
                                @if ($canViewContracts)
                                    <a
                                        href="{{ $contract['show_url'] }}"
                                        title="{{ __('catalog.tenants.kardex.view_contract') }}"
                                        aria-label="{{ __('catalog.tenants.kardex.view_contract') }}"
                                        class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state :title="__('catalog.tenants.kardex.empty_contracts')" :colspan="6" />
                    @endforelse
                </x-slot:body>
            </x-ui.table>
        @endif

        @if ($tab === 'charges')
            <div class="border-b border-slate-100 px-4 py-2">
                <p class="text-xs text-slate-500">{{ __('catalog.tenants.kardex.tab_charges_hint') }}</p>
            </div>
            <x-ui.table :flush="true">
                <x-slot:head>
                    <th class="px-4 py-3">{{ __('common.contract') }} / {{ __('common.unit') }}</th>
                    <th class="px-4 py-3">{{ __('common.type') }}</th>
                    <th class="px-4 py-3">{{ __('common.date') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('common.amount') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('contracts.paid') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('common.balance') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('common.actions') }}</th>
                </x-slot:head>
                <x-slot:body>
                    @forelse ($charges as $charge)
                        <tr wire:key="kardex-charge-{{ $charge['contract_id'] }}-{{ $charge['charge_date'] }}-{{ $charge['type'] }}" class="transition hover:bg-slate-50/80">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ $charge['unit_label'] }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <x-ui.badge variant="info">{{ $charge['type'] }}</x-ui.badge>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $charge['charge_date'] ?: __('common.n_a') }}</td>
                            <td class="px-4 py-3 text-right text-slate-900">${{ number_format($charge['amount'], 2) }}</td>
                            <td class="px-4 py-3 text-right text-slate-700">${{ number_format($charge['paid'], 2) }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-rose-700">${{ number_format($charge['balance'], 2) }}</td>
                            <td class="px-4 py-3 text-right">
                                @if ($canViewContracts)
                                    <a
                                        href="{{ $charge['contract_show_url'] }}"
                                        title="{{ __('catalog.tenants.kardex.view_contract') }}"
                                        aria-label="{{ __('catalog.tenants.kardex.view_contract') }}"
                                        class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state :title="__('catalog.tenants.kardex.empty_charges')" :colspan="7" />
                    @endforelse
                </x-slot:body>
            </x-ui.table>
        @endif

        @if ($tab === 'payments')
            <x-ui.table :flush="true">
                <x-slot:head>
                    <th class="px-4 py-3">{{ __('common.folio') }}</th>
                    <th class="px-4 py-3">{{ __('common.date') }}</th>
                    <th class="px-4 py-3">{{ __('common.method') }}</th>
                    <th class="px-4 py-3">{{ __('common.contract') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('common.amount') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('common.actions') }}</th>
                </x-slot:head>
                <x-slot:body>
                    @forelse ($payments as $payment)
                        <tr wire:key="kardex-payment-{{ $payment['id'] }}" class="transition hover:bg-slate-50/80">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $payment['folio'] ?: __('common.n_a') }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $payment['paid_at'] ?: __('common.n_a') }}</td>
                            <td class="px-4 py-3 text-slate-700">
                                @if ($payment['method'])
                                    {{ __('finance.payments.methods.'.$payment['method']) }}
                                @else
                                    {{ __('common.n_a') }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-700">#{{ $payment['contract_id'] }}</td>
                            <td class="px-4 py-3 text-right font-medium text-slate-900">${{ number_format($payment['amount'], 2) }}</td>
                            <td class="px-4 py-3 text-right">
                                @if ($canViewPayments)
                                    <a
                                        href="{{ $payment['show_url'] }}"
                                        title="{{ __('catalog.tenants.kardex.view_payment') }}"
                                        aria-label="{{ __('catalog.tenants.kardex.view_payment') }}"
                                        class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state :title="__('catalog.tenants.kardex.empty_payments')" :colspan="6" />
                    @endforelse
                </x-slot:body>
            </x-ui.table>
        @endif
    </div>

    @if ($canManageTenants)
        <x-ui.modal
            :open="$showForm"
            :title="__('catalog.tenants.edit_tenant')"
            :aria-label="__('catalog.tenants.edit_tenant')"
            max-width="2xl"
        >
            <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('common.full_name') }} *</label>
                    <input type="text" wire:model.blur="full_name" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm uppercase">
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
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('catalog.tenants.ine_clave') }}</label>
                    <input type="text" wire:model.blur="ine_clave" maxlength="18" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm uppercase" autocomplete="off">
                    @error('ine_clave') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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
</section>
