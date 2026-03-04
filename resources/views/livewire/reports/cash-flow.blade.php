<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Flujo por rango</h1>
            <p class="mt-1 text-sm text-slate-600">Fuente de verdad: allocations aplicadas a cargos operativos dentro del rango.</p>
        </div>
        <a
            href="{{ $exportUrl }}"
            class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >
            Exportar CSV
        </a>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-3">
            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Desde</label>
                <input type="date" wire:model.live="date_from" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('date_from') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Hasta</label>
                <input type="date" wire:model.live="date_to" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('date_to') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-end">
                <div class="rounded-md bg-slate-100 px-3 py-2 text-xs text-slate-600">
                    <p>Tipos operativos: <strong>{{ implode(', ', $operatingChargeTypes) }}</strong></p>
                    <p class="mt-1">Excluye: <strong>DEPOSIT_HOLD</strong> y <strong>DEPOSIT_APPLY</strong>.</p>
                </div>
            </div>
        </div>
    </div>

    @if ($closedMonthSnapshot)
        <div class="rounded-xl border {{ $snapshotMatches ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} p-4 shadow-sm">
            <p class="text-sm font-medium {{ $snapshotMatches ? 'text-emerald-800' : 'text-amber-800' }}">
                {{ $snapshotMatches ? 'Mes cerrado: el reporte coincide con el snapshot.' : 'Mes cerrado: hay diferencia contra snapshot de cierre.' }}
            </p>
            <p class="mt-1 text-xs {{ $snapshotMatches ? 'text-emerald-700' : 'text-amber-700' }}">
                Snapshot {{ number_format((float) ($closedMonthSnapshot['ingresos_operativos'] ?? 0), 2) }}
                / {{ number_format((float) ($closedMonthSnapshot['egresos'] ?? 0), 2) }}
                / {{ number_format((float) ($closedMonthSnapshot['neto'] ?? 0), 2) }}
                (ingresos/egresos/neto)
            </p>
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-3">
        <article class="rounded-xl border border-emerald-200 bg-emerald-50 p-5">
            <p class="text-xs uppercase tracking-wide text-emerald-700">Ingresos</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-900">${{ number_format($incomeTotal, 2) }}</p>
            <p class="mt-1 text-xs text-emerald-700">{{ $incomeCount }} allocations</p>
        </article>

        <article class="rounded-xl border border-rose-200 bg-rose-50 p-5">
            <p class="text-xs uppercase tracking-wide text-rose-700">Egresos</p>
            <p class="mt-2 text-2xl font-semibold text-rose-900">${{ number_format($expenseTotal, 2) }}</p>
            <p class="mt-1 text-xs text-rose-700">{{ $expenseCount }} egresos</p>
        </article>

        <article class="rounded-xl border border-slate-300 bg-white p-5">
            <p class="text-xs uppercase tracking-wide text-slate-500">Neto</p>
            <p class="mt-2 text-2xl font-semibold {{ $netTotal >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                ${{ number_format($netTotal, 2) }}
            </p>
            <p class="mt-1 text-xs text-slate-500">Ingresos - Egresos</p>
        </article>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-900">Desglose de ingresos por tipo</h2>
        <div class="mt-3 grid gap-2 md:grid-cols-3">
            @foreach ($incomeByType as $type => $amount)
                <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-xs font-medium text-slate-600">{{ $type }}</p>
                    <p class="text-sm font-semibold text-slate-900">${{ number_format((float) $amount, 2) }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Ingresos (allocations)</h2>
            <p class="text-xs text-slate-500">Mostrando {{ $incomeCount }} registros</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Fecha pago</th>
                        <th class="px-4 py-2">Folio</th>
                        <th class="px-4 py-2">Contrato</th>
                        <th class="px-4 py-2">Unidad</th>
                        <th class="px-4 py-2">Tipo</th>
                        <th class="px-4 py-2 text-right">Monto</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($incomeDetails as $row)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-4 py-2 text-slate-700">{{ \Carbon\Carbon::parse($row['paid_at'])->timezone('America/Tijuana')->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-2 text-slate-700">{{ $row['receipt_folio'] ?? '-' }}</td>
                            <td class="px-4 py-2 text-slate-700">
                                #{{ $row['contract_id'] }}
                                <p class="text-xs text-slate-500">{{ $row['tenant_name'] ?? 'Sin inquilino' }}</p>
                            </td>
                            <td class="px-4 py-2 text-slate-700">
                                {{ $row['property_name'] ?? 'Sin propiedad' }} / {{ $row['unit_name'] ?? ($row['unit_code'] ?? 'Sin unidad') }}
                            </td>
                            <td class="px-4 py-2 font-medium text-slate-700">{{ $row['charge_type'] }}</td>
                            <td class="px-4 py-2 text-right font-medium text-emerald-700">${{ number_format((float) $row['allocated_amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">Sin allocations de ingreso en el rango.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Egresos</h2>
            <p class="text-xs text-slate-500">Mostrando {{ $expenseCount }} registros</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Fecha</th>
                        <th class="px-4 py-2">Categoría</th>
                        <th class="px-4 py-2">Unidad</th>
                        <th class="px-4 py-2">Proveedor</th>
                        <th class="px-4 py-2 text-right">Monto</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($expenses as $expense)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-4 py-2 text-slate-700">{{ optional($expense->spent_at)->format('Y-m-d') }}</td>
                            <td class="px-4 py-2 text-slate-700">{{ $expense->category }}</td>
                            <td class="px-4 py-2 text-slate-700">{{ $expense->unit?->property?->name }} / {{ $expense->unit?->name ?? '-' }}</td>
                            <td class="px-4 py-2 text-slate-700">{{ $expense->vendor ?: '-' }}</td>
                            <td class="px-4 py-2 text-right font-medium text-rose-700">${{ number_format((float) $expense->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">Sin egresos en el rango.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
