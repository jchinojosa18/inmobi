<section class="space-y-6">
    <x-ui.page-header
        :title="__('dashboard.title')"
        :description="__('dashboard.description')"
    >
        <x-slot:actions>
            @if ($canCreatePayments)
                <x-ui.button type="button" onclick="Livewire.dispatch('open-quick-payment')">
                    {{ __('common.register_payment') }}
                </x-ui.button>
            @endif
            @if ($canCreateExpenses)
                <x-ui.button type="button" variant="secondary" onclick="Livewire.dispatch('open-quick-expense')">
                    {{ __('common.register_expense') }}
                </x-ui.button>
            @endif
            @if ($canManageContracts)
                <x-ui.button href="{{ route('contracts.create') }}" variant="secondary">
                    {{ __('common.new_contract') }}
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($onboardingChecklist['show'])
        <x-ui.card>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">
                        {{ __('dashboard.setup_title', [
                            'completed' => $onboardingChecklist['critical_completed'],
                            'total' => $onboardingChecklist['critical_total'],
                        ]) }}
                    </h2>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ __('dashboard.setup_description') }}
                    </p>
                </div>
                <x-ui.button type="button" variant="secondary" size="sm" wire:click="dismissOnboarding">
                    {{ __('dashboard.dismiss_onboarding') }}
                </x-ui.button>
            </div>

            <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                <div
                    class="h-full rounded-full bg-indigo-600 transition-all duration-300"
                    style="width: {{ $onboardingChecklist['critical_progress_percent'] }}%;"
                    role="progressbar"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="{{ $onboardingChecklist['critical_progress_percent'] }}"
                    aria-label="{{ __('dashboard.progress_aria') }}"
                ></div>
            </div>

            <div class="mt-5 space-y-3">
                @foreach ($onboardingChecklist['critical_steps'] as $step)
                    <article class="rounded-lg border border-slate-200 px-4 py-3">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div class="flex items-start gap-3">
                                @if ($step['complete'])
                                    <span class="mt-0.5 inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-emerald-700" aria-hidden="true">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.414l-7.4 7.4a1 1 0 01-1.414 0l-3.294-3.294a1 1 0 011.414-1.414l2.587 2.586 6.693-6.692a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                @else
                                    <span class="mt-1 inline-block h-4 w-4 rounded-full border border-slate-300 bg-slate-100" aria-hidden="true"></span>
                                @endif
                                <div>
                                    <p class="text-sm font-medium text-slate-900">{{ $step['title'] }}</p>
                                    <p class="mt-0.5 text-xs text-slate-600">{{ $step['description'] }}</p>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                @if ($step['complete'])
                                    <x-ui.badge variant="success">{{ __('common.complete') }}</x-ui.badge>
                                @else
                                    @foreach ($step['ctas'] as $cta)
                                        @if (($cta['type'] ?? '') === 'route' && isset($cta['route']))
                                            <x-ui.button href="{{ route($cta['route']) }}" variant="secondary" size="sm">
                                                {{ $cta['label'] }}
                                            </x-ui.button>
                                        @endif

                                        @if (($cta['type'] ?? '') === 'action_generate_rent')
                                            <x-ui.button type="button" size="sm" wire:click="generateCurrentMonthRent">
                                                {{ $cta['label'] }}
                                            </x-ui.button>
                                        @endif
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-5 border-t border-slate-200 pt-4">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('dashboard.recommended') }}</h3>
                <div class="mt-3 space-y-3">
                    @foreach ($onboardingChecklist['recommended_steps'] as $step)
                        <article class="rounded-lg border border-slate-200 px-4 py-3">
                            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div class="flex items-start gap-3">
                                    @if ($step['complete'])
                                        <span class="mt-0.5 inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-emerald-700" aria-hidden="true">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.414l-7.4 7.4a1 1 0 01-1.414 0l-3.294-3.294a1 1 0 011.414-1.414l2.587 2.586 6.693-6.692a1 1 0 011.414 0z" clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    @else
                                        <span class="mt-1 inline-block h-4 w-4 rounded-full border border-slate-300 bg-slate-100" aria-hidden="true"></span>
                                    @endif
                                    <div>
                                        <p class="text-sm font-medium text-slate-900">{{ $step['title'] }}</p>
                                        <p class="mt-0.5 text-xs text-slate-600">{{ $step['description'] }}</p>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($step['complete'])
                                        <x-ui.badge variant="success">{{ __('common.complete') }}</x-ui.badge>
                                    @else
                                        @foreach ($step['ctas'] as $cta)
                                            @if (($cta['type'] ?? '') === 'action_open_quick_payment')
                                                <x-ui.button type="button" variant="secondary" size="sm" onclick="Livewire.dispatch('open-quick-payment')">
                                                    {{ $cta['label'] }}
                                                </x-ui.button>
                                            @endif

                                            @if (($cta['type'] ?? '') === 'action_open_quick_expense')
                                                <x-ui.button type="button" variant="secondary" size="sm" onclick="Livewire.dispatch('open-quick-expense')">
                                                    {{ $cta['label'] }}
                                                </x-ui.button>
                                            @endif
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </x-ui.card>
    @endif

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <x-ui.stat-card
            :label="__('dashboard.income_month')"
            value="${{ number_format($incomeMonth, 2) }}"
            :hint="__('dashboard.income_hint')"
            tone="success"
        />
        <x-ui.stat-card
            :label="__('dashboard.expense_month')"
            value="${{ number_format($expenseMonth, 2) }}"
            tone="danger"
        />
        <x-ui.stat-card
            :label="__('common.net')"
            value="${{ number_format($netMonth, 2) }}"
            :value-class="$netMonth >= 0 ? 'text-emerald-700' : 'text-rose-700'"
        />
        <x-ui.stat-card
            :label="__('dashboard.overdue_portfolio')"
            value="${{ number_format($overduePortfolioTotal, 2) }}"
            :hint="__('dashboard.overdue_hint')"
            tone="warning"
        />
        <x-ui.stat-card
            :label="__('dashboard.active_contracts')"
            :value="(string) $activeContracts"
        />
        <x-ui.stat-card
            :label="__('dashboard.units')"
            :value="__('dashboard.occupied_available', ['occupied' => $occupiedUnits, 'available' => $availableUnits])"
        />
    </div>

    <div class="space-y-6">
        <x-ui.table>
            <x-slot:header>
                <h2 class="text-sm font-semibold text-slate-900">{{ __('dashboard.overdue_top10') }}</h2>
            </x-slot:header>
            <x-slot:head>
                <th class="px-4 py-3">{{ __('common.contract') }}</th>
                <th class="px-4 py-3">{{ __('common.tenant') }}</th>
                <th class="px-4 py-3">{{ __('common.unit') }}</th>
                <th class="px-4 py-3 text-right">{{ __('dashboard.overdue_days') }}</th>
                <th class="px-4 py-3 text-right">{{ __('common.balance') }}</th>
                <th class="px-4 py-3 text-right">{{ __('common.action') }}</th>
            </x-slot:head>
            <x-slot:body>
                @forelse ($overdueContracts as $row)
                    <tr wire:key="dashboard-overdue-{{ $row->contract_id }}" class="transition hover:bg-slate-50/80">
                        <td class="px-4 py-3 text-slate-700">#{{ $row->contract_id }}</td>
                        <td class="px-4 py-3 text-slate-700">
                            {{ $row->tenant_name }}
                            <p class="text-xs text-slate-500">{{ $row->tenant_phone ?: ($row->tenant_email ?: __('common.no_contact')) }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-700">
                            {{ $row->property_name }} / {{ $row->unit_name ?? ($row->unit_code ?? '-') }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <x-ui.badge variant="warning">{{ (int) $row->overdue_days }} {{ __('common.days') }}</x-ui.badge>
                        </td>
                        <td class="px-4 py-3 text-right font-medium text-slate-900">${{ number_format((float) $row->pending_balance, 2) }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($canCreatePayments)
                                <x-ui.button type="button" variant="secondary" size="sm" onclick="Livewire.dispatch('open-quick-payment', { contractId: {{ $row->contract_id }} })">
                                    {{ __('common.register_payment') }}
                                </x-ui.button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-state :title="__('dashboard.no_overdue')" :colspan="6" />
                @endforelse
            </x-slot:body>
        </x-ui.table>

        <x-ui.table>
            <x-slot:header>
                <h2 class="text-sm font-semibold text-slate-900">{{ __('dashboard.grace_top10') }}</h2>
            </x-slot:header>
            <x-slot:head>
                <th class="px-4 py-3">{{ __('common.contract') }}</th>
                <th class="px-4 py-3">{{ __('common.tenant') }}</th>
                <th class="px-4 py-3">{{ __('common.unit') }}</th>
                <th class="px-4 py-3">{{ __('dashboard.due_grace') }}</th>
                <th class="px-4 py-3 text-right">{{ __('common.balance') }}</th>
                <th class="px-4 py-3 text-right">{{ __('common.action') }}</th>
            </x-slot:head>
            <x-slot:body>
                @forelse ($graceContracts as $row)
                    <tr wire:key="dashboard-grace-{{ $row->contract_id }}" class="transition hover:bg-slate-50/80">
                        <td class="px-4 py-3 text-slate-700">#{{ $row->contract_id }}</td>
                        <td class="px-4 py-3 text-slate-700">
                            {{ $row->tenant_name }}
                            <p class="text-xs text-slate-500">{{ $row->tenant_phone ?: ($row->tenant_email ?: __('common.no_contact')) }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $row->property_name }} / {{ $row->unit_name ?? ($row->unit_code ?? '-') }}</td>
                        <td class="px-4 py-3 text-slate-700">
                            <x-ui.display-date :value="$row->due_date" />
                            <p class="text-xs text-slate-500">{{ __('dashboard.grace_until', ['date' => \App\Support\DateDisplay::formatDate($row->grace_until)]) }}</p>
                        </td>
                        <td class="px-4 py-3 text-right font-medium text-slate-900">${{ number_format((float) $row->pending_balance, 2) }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($canCreatePayments)
                                <x-ui.button type="button" variant="secondary" size="sm" onclick="Livewire.dispatch('open-quick-payment', { contractId: {{ $row->contract_id }} })">
                                    {{ __('common.register_payment') }}
                                </x-ui.button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-state :title="__('dashboard.no_grace')" :colspan="6" />
                @endforelse
            </x-slot:body>
        </x-ui.table>
    </div>

    <x-ui.table>
        <x-slot:header>
            <h2 class="text-sm font-semibold text-slate-900">{{ __('dashboard.recent_payments') }}</h2>
        </x-slot:header>
        <x-slot:head>
            <th class="px-4 py-3">{{ __('common.folio') }}</th>
            <th class="px-4 py-3">{{ __('common.date') }}</th>
            <th class="px-4 py-3">{{ __('common.contract') }}</th>
            <th class="px-4 py-3 text-right">{{ __('common.amount') }}</th>
            <th class="px-4 py-3 text-right">{{ __('common.actions') }}</th>
        </x-slot:head>
        <x-slot:body>
            @forelse ($recentPayments as $payment)
                <tr wire:key="dashboard-payment-{{ $payment->id }}" class="transition hover:bg-slate-50/80">
                    <td class="px-4 py-3 text-slate-700">{{ $payment->receipt_folio ?? __('common.n_a') }}</td>
                    <td class="px-4 py-3 text-slate-700"><x-ui.display-date :value="$payment->paid_at" time /></td>
                    <td class="px-4 py-3 text-slate-700">
                        #{{ $payment->contract_id }} · {{ $payment->tenant_name }}
                        <p class="text-xs text-slate-500">{{ $payment->property_name }} / {{ $payment->unit_name ?? ($payment->unit_code ?? '-') }}</p>
                    </td>
                    <td class="px-4 py-3 text-right font-medium text-slate-900">${{ number_format((float) $payment->amount, 2) }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="inline-flex items-center gap-2">
                            <x-ui.button href="{{ route('payments.show', $payment->payment_id) }}" variant="secondary" size="sm">{{ __('common.view_payment') }}</x-ui.button>
                            @if ($payment->receipt_folio !== null)
                                @php
                                    $receiptViewerItem = \App\Support\FileViewerItem::fromPdfRoute(
                                        'payments.receipt.pdf',
                                        ['paymentId' => $payment->payment_id],
                                        __('common.receipt_pdf'),
                                    );
                                @endphp
                                <x-ui.file-viewer-trigger
                                    :items="[$receiptViewerItem]"
                                    :index="0"
                                    variant="secondary"
                                    size="sm"
                                >
                                    {{ __('common.receipt_pdf') }}
                                </x-ui.file-viewer-trigger>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <x-ui.empty-state :title="__('dashboard.no_recent_payments')" :colspan="5" />
            @endforelse
        </x-slot:body>
    </x-ui.table>
</section>
