<section class="space-y-6">
    <x-ui.page-header
        title="Flujo por rango"
        description="Fuente de verdad: allocations aplicadas a cargos operativos dentro del rango."
    >
        <x-slot:actions>
            <x-ui.button href="{{ $exportUrl }}" variant="secondary">
                Exportar CSV
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="true" class="!p-4">
        <div class="grid gap-3 md:grid-cols-3">
            <div>
                <x-ui.input
                    id="cash-flow-date-from"
                    label="Desde"
                    type="date"
                    wire:model.live="date_from"
                />
                @error('date_from') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-ui.input
                    id="cash-flow-date-to"
                    label="Hasta"
                    type="date"
                    wire:model.live="date_to"
                />
                @error('date_to') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-end">
                <div class="rounded-md bg-slate-100 px-3 py-2 text-xs text-slate-600">
                    <p>Tipos operativos: <strong>{{ implode(', ', $operatingChargeTypes) }}</strong></p>
                    <p class="mt-1">Excluye: <strong>DEPOSIT_HOLD</strong> y <strong>DEPOSIT_APPLY</strong>.</p>
                </div>
            </div>
        </div>
    </x-ui.card>

    @if ($closedMonthSnapshot)
        <x-ui.card
            :padding="true"
            class="!p-4 {{ $snapshotMatches ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }}"
        >
            <p class="text-sm font-medium {{ $snapshotMatches ? 'text-emerald-800' : 'text-amber-800' }}">
                {{ $snapshotMatches ? 'Mes cerrado: el reporte coincide con el snapshot.' : 'Mes cerrado: hay diferencia contra snapshot de cierre.' }}
            </p>
            <p class="mt-1 text-xs {{ $snapshotMatches ? 'text-emerald-700' : 'text-amber-700' }}">
                Snapshot {{ number_format((float) ($closedMonthSnapshot['ingresos_operativos'] ?? 0), 2) }}
                / {{ number_format((float) ($closedMonthSnapshot['egresos'] ?? 0), 2) }}
                / {{ number_format((float) ($closedMonthSnapshot['neto'] ?? 0), 2) }}
                (ingresos/egresos/neto)
            </p>
        </x-ui.card>
    @endif

    <div class="grid gap-4 md:grid-cols-3">
        <x-ui.stat-card
            label="Ingresos"
            value="${{ number_format($incomeTotal, 2) }}"
            :hint="$incomeCount . ' allocations'"
            tone="success"
            value-class="text-emerald-900"
        />
        <x-ui.stat-card
            label="Egresos"
            value="${{ number_format($expenseTotal, 2) }}"
            :hint="$expenseCount . ' egresos'"
            tone="danger"
            value-class="text-rose-900"
        />
        <x-ui.stat-card
            label="Neto"
            value="${{ number_format($netTotal, 2) }}"
            hint="Ingresos - Egresos"
            :value-class="$netTotal >= 0 ? 'text-emerald-700' : 'text-rose-700'"
        />
    </div>

    <x-ui.card :padding="true" class="!p-4">
        <h2 class="text-sm font-semibold text-slate-900">Desglose de ingresos por tipo</h2>
        <div class="mt-3 grid gap-2 md:grid-cols-3">
            @foreach ($incomeByType as $type => $amount)
                <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-xs font-medium text-slate-600">{{ $type }}</p>
                    <p class="text-sm font-semibold text-slate-900">${{ number_format((float) $amount, 2) }}</p>
                </div>
            @endforeach
        </div>
    </x-ui.card>

    <x-ui.table>
        <x-slot:header>
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">Ingresos (allocations)</h2>
                <p class="text-xs text-slate-500">Mostrando {{ $incomeCount }} registros</p>
            </div>
        </x-slot:header>
        <x-slot:head>
            <th class="px-4 py-3">Fecha pago</th>
            <th class="px-4 py-3">Folio</th>
            <th class="px-4 py-3">Contrato</th>
            <th class="px-4 py-3">Unidad</th>
            <th class="px-4 py-3">Tipo</th>
            <th class="px-4 py-3 text-right">Monto</th>
        </x-slot:head>
        <x-slot:body>
            @forelse ($incomeDetails as $row)
                <tr class="transition hover:bg-slate-50/80">
                    <td class="px-4 py-3 text-slate-700">{{ \Carbon\Carbon::parse($row['paid_at'])->timezone('America/Tijuana')->format('Y-m-d H:i') }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $row['receipt_folio'] ?? '-' }}</td>
                    <td class="px-4 py-3 text-slate-700">
                        #{{ $row['contract_id'] }}
                        <p class="text-xs text-slate-500">{{ $row['tenant_name'] ?? 'Sin inquilino' }}</p>
                    </td>
                    <td class="px-4 py-3 text-slate-700">
                        {{ $row['property_name'] ?? 'Sin propiedad' }} / {{ $row['unit_name'] ?? ($row['unit_code'] ?? 'Sin unidad') }}
                    </td>
                    <td class="px-4 py-3 font-medium text-slate-700">{{ $row['charge_type'] }}</td>
                    <td class="px-4 py-3 text-right font-medium text-emerald-700">${{ number_format((float) $row['allocated_amount'], 2) }}</td>
                </tr>
            @empty
                <x-ui.empty-state title="Sin allocations de ingreso en el rango." :colspan="6" />
            @endforelse
        </x-slot:body>
    </x-ui.table>

    <x-ui.table>
        <x-slot:header>
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">Egresos</h2>
                <p class="text-xs text-slate-500">Mostrando {{ $expenseCount }} registros</p>
            </div>
        </x-slot:header>
        <x-slot:head>
            <th class="px-4 py-3">Fecha</th>
            <th class="px-4 py-3">Categoría</th>
            <th class="px-4 py-3">Unidad</th>
            <th class="px-4 py-3">Proveedor</th>
            <th class="px-4 py-3 text-right">Monto</th>
        </x-slot:head>
        <x-slot:body>
            @forelse ($expenses as $expense)
                <tr class="transition hover:bg-slate-50/80">
                    <td class="px-4 py-3 text-slate-700">{{ optional($expense->spent_at)->format('Y-m-d') }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $expense->category }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $expense->unit?->property?->name }} / {{ $expense->unit?->name ?? '-' }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $expense->vendor ?: '-' }}</td>
                    <td class="px-4 py-3 text-right font-medium text-rose-700">${{ number_format((float) $expense->amount, 2) }}</td>
                </tr>
            @empty
                <x-ui.empty-state title="Sin egresos en el rango." :colspan="5" />
            @endforelse
        </x-slot:body>
    </x-ui.table>
</section>
