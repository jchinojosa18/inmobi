<section class="space-y-6">
    <x-ui.page-header
        title="Dashboard operativo"
        description="Centro de control operativo para administración diaria."
    >
        <x-slot:actions>
            @if ($canCreatePayments)
                <x-ui.button type="button" onclick="Livewire.dispatch('open-quick-payment')">
                    Registrar pago
                </x-ui.button>
            @endif
            @if ($canCreateExpenses)
                <x-ui.button type="button" variant="secondary" onclick="Livewire.dispatch('open-quick-expense')">
                    Registrar egreso
                </x-ui.button>
            @endif
            @if ($canManageContracts)
                <x-ui.button href="{{ route('contracts.create') }}" variant="secondary">
                    Nuevo contrato
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($onboardingChecklist['show'])
        <x-ui.card>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">
                        Configura tu sistema ({{ $onboardingChecklist['critical_completed'] }}/{{ $onboardingChecklist['critical_total'] }})
                    </h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Completa estos pasos para evitar un dashboard vacío y activar operación diaria.
                    </p>
                </div>
                <x-ui.button type="button" variant="secondary" size="sm" wire:click="dismissOnboarding">
                    Ocultar por ahora
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
                    aria-label="Progreso del checklist inicial"
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
                                    <x-ui.badge variant="success">Completo</x-ui.badge>
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
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Recomendados</h3>
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
                                        <x-ui.badge variant="success">Completo</x-ui.badge>
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
            label="Ingresos operativos del mes"
            value="${{ number_format($incomeMonth, 2) }}"
            hint="Allocations (sin depósitos)"
            tone="success"
        />
        <x-ui.stat-card
            label="Egresos del mes"
            value="${{ number_format($expenseMonth, 2) }}"
            tone="danger"
        />
        <x-ui.stat-card
            label="Neto"
            value="${{ number_format($netMonth, 2) }}"
            :value-class="$netMonth >= 0 ? 'text-emerald-700' : 'text-rose-700'"
        />
        <x-ui.stat-card
            label="Cartera vencida total"
            value="${{ number_format($overduePortfolioTotal, 2) }}"
            hint="Contratos con renta vencida"
            tone="warning"
        />
        <x-ui.stat-card
            label="Contratos activos"
            :value="(string) $activeContracts"
        />
        <x-ui.stat-card
            label="Unidades"
            value="{{ $occupiedUnits }} ocupadas / {{ $availableUnits }} disponibles"
        />
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <x-ui.table>
            <x-slot:header>
                <h2 class="text-sm font-semibold text-slate-900">Vencidos (top 10)</h2>
            </x-slot:header>
            <x-slot:head>
                <th class="px-4 py-3">Contrato</th>
                <th class="px-4 py-3">Unidad</th>
                <th class="px-4 py-3">Inquilino</th>
                <th class="px-4 py-3 text-right">Días atraso</th>
                <th class="px-4 py-3 text-right">Saldo</th>
                <th class="px-4 py-3 text-right">Acción</th>
            </x-slot:head>
            <x-slot:body>
                @forelse ($overdueContracts as $row)
                    <tr class="transition hover:bg-slate-50/80">
                        <td class="px-4 py-3 text-slate-700">#{{ $row->contract_id }}</td>
                        <td class="px-4 py-3 text-slate-700">
                            {{ $row->property_name }} / {{ $row->unit_name ?? ($row->unit_code ?? '-') }}
                        </td>
                        <td class="px-4 py-3 text-slate-700">
                            {{ $row->tenant_name }}
                            <p class="text-xs text-slate-500">{{ $row->tenant_phone ?: ($row->tenant_email ?: 'Sin contacto') }}</p>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <x-ui.badge variant="warning">{{ (int) $row->overdue_days }} días</x-ui.badge>
                        </td>
                        <td class="px-4 py-3 text-right font-medium text-slate-900">${{ number_format((float) $row->pending_balance, 2) }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($canCreatePayments)
                                <x-ui.button type="button" variant="secondary" size="sm" onclick="Livewire.dispatch('open-quick-payment', { contractId: {{ $row->contract_id }} })">
                                    Registrar pago
                                </x-ui.button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-state title="Sin contratos vencidos." :colspan="6" />
                @endforelse
            </x-slot:body>
        </x-ui.table>

        <x-ui.table>
            <x-slot:header>
                <h2 class="text-sm font-semibold text-slate-900">En gracia (top 10)</h2>
            </x-slot:header>
            <x-slot:head>
                <th class="px-4 py-3">Contrato</th>
                <th class="px-4 py-3">Unidad</th>
                <th class="px-4 py-3">Vence / gracia</th>
                <th class="px-4 py-3 text-right">Saldo</th>
                <th class="px-4 py-3 text-right">Acción</th>
            </x-slot:head>
            <x-slot:body>
                @forelse ($graceContracts as $row)
                    <tr class="transition hover:bg-slate-50/80">
                        <td class="px-4 py-3 text-slate-700">#{{ $row->contract_id }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $row->property_name }} / {{ $row->unit_name ?? ($row->unit_code ?? '-') }}</td>
                        <td class="px-4 py-3 text-slate-700">
                            {{ \Carbon\Carbon::parse($row->due_date)->format('Y-m-d') }}
                            <p class="text-xs text-slate-500">Gracia: {{ \Carbon\Carbon::parse($row->grace_until)->format('Y-m-d') }}</p>
                        </td>
                        <td class="px-4 py-3 text-right font-medium text-slate-900">${{ number_format((float) $row->pending_balance, 2) }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($canCreatePayments)
                                <x-ui.button type="button" variant="secondary" size="sm" onclick="Livewire.dispatch('open-quick-payment', { contractId: {{ $row->contract_id }} })">
                                    Registrar pago
                                </x-ui.button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-state title="Sin contratos en gracia." :colspan="5" />
                @endforelse
            </x-slot:body>
        </x-ui.table>
    </div>

    <x-ui.table>
        <x-slot:header>
            <h2 class="text-sm font-semibold text-slate-900">Pagos recientes (top 10)</h2>
        </x-slot:header>
        <x-slot:head>
            <th class="px-4 py-3">Folio</th>
            <th class="px-4 py-3">Fecha</th>
            <th class="px-4 py-3">Contrato</th>
            <th class="px-4 py-3 text-right">Monto</th>
            <th class="px-4 py-3 text-right">Acciones</th>
        </x-slot:head>
        <x-slot:body>
            @forelse ($recentPayments as $payment)
                <tr class="transition hover:bg-slate-50/80">
                    <td class="px-4 py-3 text-slate-700">{{ $payment->receipt_folio }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ \Carbon\Carbon::parse($payment->paid_at)->timezone('America/Tijuana')->format('Y-m-d H:i') }}</td>
                    <td class="px-4 py-3 text-slate-700">
                        #{{ $payment->contract_id }} · {{ $payment->tenant_name }}
                        <p class="text-xs text-slate-500">{{ $payment->property_name }} / {{ $payment->unit_name ?? ($payment->unit_code ?? '-') }}</p>
                    </td>
                    <td class="px-4 py-3 text-right font-medium text-slate-900">${{ number_format((float) $payment->amount, 2) }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="inline-flex items-center gap-2">
                            <x-ui.button href="{{ route('payments.show', $payment->payment_id) }}" variant="secondary" size="sm">Ver pago</x-ui.button>
                            <x-ui.button href="{{ route('payments.receipt.pdf', ['paymentId' => $payment->payment_id]) }}" variant="secondary" size="sm">Recibo PDF</x-ui.button>
                        </div>
                    </td>
                </tr>
            @empty
                <x-ui.empty-state title="Sin pagos recientes." :colspan="5" />
            @endforelse
        </x-slot:body>
    </x-ui.table>
</section>
