<section class="space-y-6">
    <x-ui.page-header
        :title="'Pago '.$payment->receipt_folio"
        :description="'Contrato #'.$payment->contract_id.' · '.$payment->contract->tenant->full_name"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('contracts.show', $payment->contract_id) }}" variant="secondary">
                Volver al contrato
            </x-ui.button>
            <x-ui.button href="{{ $receiptUrl }}" variant="secondary" target="_blank" rel="noopener noreferrer">
                Ver PDF
            </x-ui.button>
            <x-ui.button
                href="{{ $whatsAppUrl }}"
                target="_blank"
                rel="noopener noreferrer"
                class="!border-0 !bg-emerald-600 !text-white hover:!bg-emerald-500"
            >
                Abrir WhatsApp
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 md:grid-cols-4">
        <x-ui.stat-card
            label="Monto"
            value="${{ number_format((float) $payment->amount, 2) }}"
        />
        <x-ui.stat-card
            label="Método"
            :value="$payment->method"
        />
        <x-ui.stat-card
            label="Aplicado"
            value="${{ number_format($receipt['allocated_total'], 2) }}"
        />
        <x-ui.stat-card
            label="Saldo a favor"
            value="${{ number_format($receipt['credited_amount'], 2) }}"
        />
    </div>

    <x-ui.table>
        <x-slot:header>
            <h2 class="text-sm font-semibold text-slate-900">Desglose de allocations</h2>
        </x-slot:header>
        <x-slot:head>
            <th class="px-4 py-3">Tipo</th>
            <th class="px-4 py-3">Periodo</th>
            <th class="px-4 py-3">Fecha cargo</th>
            <th class="px-4 py-3 text-right">Monto aplicado</th>
        </x-slot:head>
        <x-slot:body>
            @forelse ($receipt['allocations'] as $allocation)
                <tr class="transition hover:bg-slate-50/80">
                    <td class="px-4 py-3">{{ $allocation['charge_type'] }}</td>
                    <td class="px-4 py-3">{{ $allocation['period'] ?: 'N/A' }}</td>
                    <td class="px-4 py-3">{{ $allocation['charge_date'] ?: 'N/A' }}</td>
                    <td class="px-4 py-3 text-right font-medium">${{ number_format($allocation['amount'], 2) }}</td>
                </tr>
            @empty
                <x-ui.empty-state title="Sin allocations registradas." :colspan="4" />
            @endforelse
        </x-slot:body>
    </x-ui.table>

    <x-ui.card>
        <h2 class="text-lg font-semibold text-slate-900">Compartir recibo</h2>
        <p class="mt-1 text-sm text-slate-600">MVP: envío manual por email o WhatsApp.</p>

        <div class="mt-4 grid gap-3 md:grid-cols-2">
            <div>
                <x-ui.input
                    id="payment-email-recipient"
                    label="Correo destino"
                    type="email"
                    wire:model.blur="emailRecipient"
                />
                @error('emailRecipient') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                <x-ui.button type="button" wire:click="sendEmail" class="mt-3">
                    Enviar por email
                </x-ui.button>
                <p class="mt-2 text-xs text-slate-500">En desarrollo, revisa Mailpit para validar envío.</p>
            </div>
            <div>
                <p class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500">Link compartible (7 días)</p>
                <textarea readonly rows="4" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs">{{ $shareUrl }}</textarea>
            </div>
        </div>
    </x-ui.card>

    @if ($documents->isNotEmpty())
        <x-ui.card>
            <h2 class="text-lg font-semibold text-slate-900">Evidencias adjuntas</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($documents as $document)
                    <li>
                        <a href="{{ $document['url'] }}" target="_blank" rel="noopener noreferrer" class="text-blue-700 underline">
                            {{ $document['path'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif
</section>
