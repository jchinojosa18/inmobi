<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Contrato #{{ $contract->id }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                {{ $contract->tenant->full_name }} · {{ $contract->unit->property->name }} / {{ $contract->unit->name }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a
                href="{{ route('contracts.payments.create', $contract) }}"
                class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500"
            >
                Registrar pago
            </a>
            <a
                href="{{ route('contracts.edit', $contract) }}"
                class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
            >
                Editar contrato
            </a>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-4">
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Estado</p>
            <p class="mt-2 text-lg font-semibold text-slate-900">{{ strtoupper($contract->status) }}</p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Cargos acumulados</p>
            <p class="mt-2 text-lg font-semibold text-slate-900">${{ number_format($chargesTotal, 2) }}</p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Aplicado</p>
            <p class="mt-2 text-lg font-semibold text-slate-900">${{ number_format($allocatedTotal, 2) }}</p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Saldo a favor</p>
            <p class="mt-2 text-lg font-semibold text-slate-900">${{ number_format($creditTotal, 2) }}</p>
        </article>
    </div>

    <livewire:contracts.deposit-hold-form
        :contract="$contract"
        :key="'deposit-hold-'.$contract->id"
    />

    <livewire:contracts.settlement-wizard
        :contract="$contract"
        :key="'settlement-wizard-'.$contract->id"
    />

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Crear ajuste</h2>
        <p class="mt-1 text-sm text-slate-600">
            Para periodos cerrados no se editan movimientos previos. Registra un cargo tipo ADJUSTMENT con razón obligatoria.
        </p>

        <form wire:submit="createAdjustment" class="mt-4 grid gap-4 md:grid-cols-2">
            @error('adjustment_month_close')
                <div class="md:col-span-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ $message }}
                </div>
            @enderror

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Monto (+/-) *</label>
                <input type="number" step="0.01" wire:model.blur="adjustment_amount" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('adjustment_amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Fecha *</label>
                <input type="date" wire:model.blur="adjustment_charge_date" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('adjustment_charge_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Razón *</label>
                <input type="text" wire:model.blur="adjustment_reason" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('adjustment_reason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Referencia vinculada (opcional)</label>
                <input type="text" wire:model.blur="adjustment_linked_to" placeholder="payment:15 | charge:20 | expense:9" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('adjustment_linked_to') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Comentario</label>
                <textarea wire:model.blur="adjustment_comment" rows="2" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                @error('adjustment_comment') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Registrar ajuste
                </button>
            </div>
        </form>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-slate-900">Estado de cuenta</h2>
            <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Saldo pendiente</p>
                <p class="font-semibold text-slate-900">${{ number_format($pendingBalance, 2) }}</p>
            </div>
        </div>

        <div class="mt-4 space-y-4">
            @forelse ($ledgerGroups as $group)
                <div class="overflow-hidden rounded-lg border border-slate-200">
                    <div class="grid gap-2 bg-slate-50 px-4 py-3 text-sm sm:grid-cols-4">
                        <p class="font-semibold text-slate-900">Periodo: {{ $group['period_label'] }}</p>
                        <p class="text-slate-600">Cargos: <span class="font-medium text-slate-900">${{ number_format($group['charges_total'], 2) }}</span></p>
                        <p class="text-slate-600">Pagado: <span class="font-medium text-slate-900">${{ number_format($group['paid_total'], 2) }}</span></p>
                        <p class="text-slate-600">Saldo: <span class="font-medium text-slate-900">${{ number_format($group['balance_total'], 2) }}</span></p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-white text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Periodo</th>
                                    <th class="px-4 py-3">Tipo</th>
                                    <th class="px-4 py-3">Fecha cargo</th>
                                    <th class="px-4 py-3">Vence</th>
                                    <th class="px-4 py-3 text-right">Monto</th>
                                    <th class="px-4 py-3 text-right">Pagado</th>
                                    <th class="px-4 py-3 text-right">Saldo</th>
                                    <th class="px-4 py-3">Estatus</th>
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
                                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium
                                                {{ $row['status_tone'] === 'red' ? 'bg-red-100 text-red-700' : '' }}
                                                {{ $row['status_tone'] === 'amber' ? 'bg-amber-100 text-amber-700' : '' }}
                                                {{ $row['status_tone'] === 'emerald' ? 'bg-emerald-100 text-emerald-700' : '' }}
                                                {{ $row['status_tone'] === 'blue' ? 'bg-blue-100 text-blue-700' : '' }}
                                                {{ $row['status_tone'] === 'slate' ? 'bg-slate-200 text-slate-700' : '' }}
                                            ">
                                                {{ $row['status_label'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <p class="rounded-md border border-slate-200 bg-slate-50 px-4 py-6 text-center text-slate-500">
                    Sin cargos registrados para este contrato.
                </p>
            @endforelse
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Pagos recientes</h2>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Folio</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Método</th>
                        <th class="px-4 py-3 text-right">Monto</th>
                        <th class="px-4 py-3 text-right">Aplicado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($payments as $payment)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $payment['folio'] }}</td>
                            <td class="px-4 py-3">{{ optional($payment['paid_at'])->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3">{{ $payment['method'] }}</td>
                            <td class="px-4 py-3 text-right">${{ number_format($payment['amount'], 2) }}</td>
                            <td class="px-4 py-3 text-right">${{ number_format($payment['allocated_amount'], 2) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <a
                                        href="{{ $payment['show_url'] }}"
                                        class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                    >
                                        Ver pago
                                    </a>
                                    <a
                                        href="{{ $payment['receipt_url'] }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                    >
                                        Ver recibo PDF
                                    </a>
                                    <a
                                        href="{{ $payment['share_url'] }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800"
                                    >
                                        Link compartible
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-500">Aún no hay pagos registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <livewire:documents.panel
        :documentable-type="\App\Models\Contract::class"
        :documentable-id="$contract->id"
        title="Documentos del contrato"
        :key="'contract-documents-'.$contract->id"
    />
</section>
