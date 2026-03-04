<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Dashboard operativo</h1>
            <p class="mt-1 text-sm text-slate-600">Centro de control operativo para administración diaria.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('contracts.index') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Registrar pago
            </a>
            <a href="{{ route('expenses.index') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Registrar egreso
            </a>
            <a href="{{ route('contracts.create') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Nuevo contrato
            </a>
        </div>
    </div>

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
                                    <a href="{{ route('contracts.payments.create', $row->contract_id) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                        Registrar pago
                                    </a>
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
                                    <a href="{{ route('contracts.payments.create', $row->contract_id) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                        Registrar pago
                                    </a>
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

