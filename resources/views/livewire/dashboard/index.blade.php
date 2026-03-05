<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Dashboard operativo</h1>
            <p class="mt-1 text-sm text-slate-600">Centro de control operativo para administración diaria.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" onclick="Livewire.dispatch('open-quick-payment')" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Registrar pago
            </button>
            <button type="button" onclick="Livewire.dispatch('open-quick-expense')" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Registrar egreso
            </button>
            <a href="{{ route('contracts.create') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Nuevo contrato
            </a>
        </div>
    </div>

    @if ($onboardingChecklist['show'])
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">
                        Configura tu sistema ({{ $onboardingChecklist['critical_completed'] }}/{{ $onboardingChecklist['critical_total'] }})
                    </h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Completa estos pasos para evitar un dashboard vacío y activar operación diaria.
                    </p>
                </div>
                <button
                    type="button"
                    wire:click="dismissOnboarding"
                    class="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-300"
                >
                    Ocultar por ahora
                </button>
            </div>

            <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                <div
                    class="h-full rounded-full bg-slate-900 transition-all duration-300"
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
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">Completo</span>
                                @else
                                    @foreach ($step['ctas'] as $cta)
                                        @if (($cta['type'] ?? '') === 'route' && isset($cta['route']))
                                            <a
                                                href="{{ route($cta['route']) }}"
                                                class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-300"
                                            >
                                                {{ $cta['label'] }}
                                            </a>
                                        @endif

                                        @if (($cta['type'] ?? '') === 'action_generate_rent')
                                            <button
                                                type="button"
                                                wire:click="generateCurrentMonthRent"
                                                class="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
                                            >
                                                {{ $cta['label'] }}
                                            </button>
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
                                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">Completo</span>
                                    @else
                                        @foreach ($step['ctas'] as $cta)
                                            @if (($cta['type'] ?? '') === 'action_open_quick_payment')
                                                <button
                                                    type="button"
                                                    onclick="Livewire.dispatch('open-quick-payment')"
                                                    class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-300"
                                                >
                                                    {{ $cta['label'] }}
                                                </button>
                                            @endif

                                            @if (($cta['type'] ?? '') === 'action_open_quick_expense')
                                                <button
                                                    type="button"
                                                    onclick="Livewire.dispatch('open-quick-expense')"
                                                    class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-300"
                                                >
                                                    {{ $cta['label'] }}
                                                </button>
                                            @endif
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <article class="rounded-xl border border-emerald-200 bg-emerald-50 p-5">
            <p class="text-xs uppercase tracking-wide text-emerald-700">Ingresos operativos del mes</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-900">${{ number_format($incomeMonth, 2) }}</p>
            <p class="mt-1 text-xs text-emerald-700">Allocations (sin depósitos)</p>
        </article>

        <article class="rounded-xl border border-rose-200 bg-rose-50 p-5">
            <p class="text-xs uppercase tracking-wide text-rose-700">Egresos del mes</p>
            <p class="mt-2 text-2xl font-semibold text-rose-900">${{ number_format($expenseMonth, 2) }}</p>
        </article>

        <article class="rounded-xl border border-slate-300 bg-white p-5">
            <p class="text-xs uppercase tracking-wide text-slate-500">Neto</p>
            <p class="mt-2 text-2xl font-semibold {{ $netMonth >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                ${{ number_format($netMonth, 2) }}
            </p>
        </article>

        <article class="rounded-xl border border-amber-200 bg-amber-50 p-5">
            <p class="text-xs uppercase tracking-wide text-amber-700">Cartera vencida total</p>
            <p class="mt-2 text-2xl font-semibold text-amber-900">${{ number_format($overduePortfolioTotal, 2) }}</p>
            <p class="mt-1 text-xs text-amber-700">Contratos con renta vencida</p>
        </article>

        <article class="rounded-xl border border-slate-300 bg-white p-5">
            <p class="text-xs uppercase tracking-wide text-slate-500">Contratos activos</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $activeContracts }}</p>
        </article>

        <article class="rounded-xl border border-slate-300 bg-white p-5">
            <p class="text-xs uppercase tracking-wide text-slate-500">Unidades</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $occupiedUnits }} ocupadas / {{ $availableUnits }} disponibles</p>
        </article>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <h2 class="text-sm font-semibold text-slate-900">Vencidos (top 10)</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2">Contrato</th>
                            <th class="px-4 py-2">Unidad</th>
                            <th class="px-4 py-2">Inquilino</th>
                            <th class="px-4 py-2 text-right">Días atraso</th>
                            <th class="px-4 py-2 text-right">Saldo</th>
                            <th class="px-4 py-2 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($overdueContracts as $row)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-4 py-2 text-slate-700">#{{ $row->contract_id }}</td>
                                <td class="px-4 py-2 text-slate-700">
                                    {{ $row->property_name }} / {{ $row->unit_name ?? ($row->unit_code ?? '-') }}
                                </td>
                                <td class="px-4 py-2 text-slate-700">
                                    {{ $row->tenant_name }}
                                    <p class="text-xs text-slate-500">{{ $row->tenant_phone ?: ($row->tenant_email ?: 'Sin contacto') }}</p>
                                </td>
                                <td class="px-4 py-2 text-right font-medium text-amber-700">{{ (int) $row->overdue_days }}</td>
                                <td class="px-4 py-2 text-right font-medium text-slate-900">${{ number_format((float) $row->pending_balance, 2) }}</td>
                                <td class="px-4 py-2 text-right">
                                    <button type="button" onclick="Livewire.dispatch('open-quick-payment', { contractId: {{ $row->contract_id }} })" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                        Registrar pago
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">Sin contratos vencidos.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <h2 class="text-sm font-semibold text-slate-900">En gracia (top 10)</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2">Contrato</th>
                            <th class="px-4 py-2">Unidad</th>
                            <th class="px-4 py-2">Vence / gracia</th>
                            <th class="px-4 py-2 text-right">Saldo</th>
                            <th class="px-4 py-2 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($graceContracts as $row)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-4 py-2 text-slate-700">#{{ $row->contract_id }}</td>
                                <td class="px-4 py-2 text-slate-700">{{ $row->property_name }} / {{ $row->unit_name ?? ($row->unit_code ?? '-') }}</td>
                                <td class="px-4 py-2 text-slate-700">
                                    {{ \Carbon\Carbon::parse($row->due_date)->format('Y-m-d') }}
                                    <p class="text-xs text-slate-500">Gracia: {{ \Carbon\Carbon::parse($row->grace_until)->format('Y-m-d') }}</p>
                                </td>
                                <td class="px-4 py-2 text-right font-medium text-slate-900">${{ number_format((float) $row->pending_balance, 2) }}</td>
                                <td class="px-4 py-2 text-right">
                                    <button type="button" onclick="Livewire.dispatch('open-quick-payment', { contractId: {{ $row->contract_id }} })" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                        Registrar pago
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">Sin contratos en gracia.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Pagos recientes (top 10)</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Folio</th>
                        <th class="px-4 py-2">Fecha</th>
                        <th class="px-4 py-2">Contrato</th>
                        <th class="px-4 py-2 text-right">Monto</th>
                        <th class="px-4 py-2 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($recentPayments as $payment)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-4 py-2 text-slate-700">{{ $payment->receipt_folio }}</td>
                            <td class="px-4 py-2 text-slate-700">{{ \Carbon\Carbon::parse($payment->paid_at)->timezone('America/Tijuana')->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-2 text-slate-700">
                                #{{ $payment->contract_id }} · {{ $payment->tenant_name }}
                                <p class="text-xs text-slate-500">{{ $payment->property_name }} / {{ $payment->unit_name ?? ($payment->unit_code ?? '-') }}</p>
                            </td>
                            <td class="px-4 py-2 text-right font-medium text-slate-900">${{ number_format((float) $payment->amount, 2) }}</td>
                            <td class="px-4 py-2 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('payments.show', $payment->payment_id) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Ver pago</a>
                                    <a href="{{ route('payments.receipt.pdf', ['paymentId' => $payment->payment_id]) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Recibo PDF</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">Sin pagos recientes.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
