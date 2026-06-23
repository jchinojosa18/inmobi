<section class="space-y-6">
    <x-ui.page-header
        :title="'Contrato #'.$contract->id"
        :description="$contract->tenant->full_name.' · '.$contract->unit->property->name.' / '.$contract->unit->name"
    >
        <x-slot:actions>
            @if ($canCreatePayments)
                <x-ui.button href="{{ route('contracts.payments.create', $contract) }}" variant="accent">
                    Registrar pago
                </x-ui.button>
            @endif
            @if ($canManageContracts)
                <x-ui.button href="{{ route('contracts.edit', $contract) }}">
                    Editar contrato
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 md:grid-cols-4">
        <x-ui.stat-card label="Estado" :value="strtoupper($contract->status)" />
        <x-ui.stat-card label="Cargos acumulados" :value="'$'.number_format($chargesTotal, 2)" />
        <x-ui.stat-card label="Aplicado" :value="'$'.number_format($allocatedTotal, 2)" />
        <x-ui.stat-card label="Saldo a favor" :value="'$'.number_format($creditTotal, 2)" />
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
                    <x-ui.input label="Monto (+/-) *" type="number" step="0.01" wire:model.blur="adjustment_amount" />
                    @error('adjustment_amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-ui.input label="Fecha *" type="date" wire:model.blur="adjustment_charge_date" />
                    @error('adjustment_charge_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-ui.input label="Razón *" type="text" wire:model.blur="adjustment_reason" />
                    @error('adjustment_reason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-ui.input label="Referencia vinculada (opcional)" type="text" wire:model.blur="adjustment_linked_to" placeholder="payment:15 | charge:20 | expense:9" />
                    @error('adjustment_linked_to') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500">Comentario</label>
                    <textarea wire:model.blur="adjustment_comment" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea>
                    @error('adjustment_comment') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2 flex justify-end">
                    <x-ui.button type="submit">
                        Registrar ajuste
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card>
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
                    Sin cargos registrados para este contrato.
                </p>
            @endforelse
        </div>
    </x-ui.card>

    <x-ui.card :padding="false">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Pagos recientes</h2>
        </div>

        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-3">Folio</th>
                <th class="px-4 py-3">Fecha</th>
                <th class="px-4 py-3">Método</th>
                <th class="px-4 py-3 text-right">Monto</th>
                <th class="px-4 py-3 text-right">Aplicado</th>
                <th class="px-4 py-3 text-right">Acciones</th>
            </x-slot:head>
            <x-slot:body>
                @forelse ($payments as $payment)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $payment['folio'] }}</td>
                        <td class="px-4 py-3">{{ optional($payment['paid_at'])->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3">{{ $payment['method'] }}</td>
                        <td class="px-4 py-3 text-right">${{ number_format($payment['amount'], 2) }}</td>
                        <td class="px-4 py-3 text-right">${{ number_format($payment['allocated_amount'], 2) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                @if ($canViewPayments)
                                    <x-ui.button href="{{ $payment['show_url'] }}" variant="secondary" size="sm">
                                        Ver pago
                                    </x-ui.button>
                                    <x-ui.button href="{{ $payment['receipt_url'] }}" variant="secondary" size="sm" target="_blank" rel="noopener noreferrer">
                                        Ver recibo PDF
                                    </x-ui.button>
                                @endif
                                <x-ui.button href="{{ $payment['share_url'] }}" size="sm" target="_blank" rel="noopener noreferrer">
                                    Link compartible
                                </x-ui.button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-state title="Aún no hay pagos registrados." :colspan="6" />
                @endforelse
            </x-slot:body>
        </x-ui.table>
    </x-ui.card>

    <livewire:documents.panel
        :documentable-type="\App\Models\Contract::class"
        :documentable-id="$contract->id"
        title="Documentos del contrato"
        :key="'contract-documents-'.$contract->id"
    />
</section>
