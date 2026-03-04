<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Pago {{ $payment->receipt_folio }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                Contrato #{{ $payment->contract_id }} · {{ $payment->contract->tenant->full_name }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a
                href="{{ route('contracts.show', $payment->contract_id) }}"
                class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                Volver al contrato
            </a>
            <a
                href="{{ $receiptUrl }}"
                target="_blank"
                rel="noopener noreferrer"
                class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                Ver PDF
            </a>
            <a
                href="{{ $whatsAppUrl }}"
                target="_blank"
                rel="noopener noreferrer"
                class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500"
            >
                Abrir WhatsApp
            </a>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-4">
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Monto</p>
            <p class="mt-2 text-lg font-semibold text-slate-900">${{ number_format((float) $payment->amount, 2) }}</p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Método</p>
            <p class="mt-2 text-lg font-semibold text-slate-900">{{ $payment->method }}</p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Aplicado</p>
            <p class="mt-2 text-lg font-semibold text-slate-900">${{ number_format($receipt['allocated_total'], 2) }}</p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Saldo a favor</p>
            <p class="mt-2 text-lg font-semibold text-slate-900">${{ number_format($receipt['credited_amount'], 2) }}</p>
        </article>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Desglose de allocations</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Periodo</th>
                        <th class="px-4 py-3">Fecha cargo</th>
                        <th class="px-4 py-3 text-right">Monto aplicado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($receipt['allocations'] as $allocation)
                        <tr>
                            <td class="px-4 py-3">{{ $allocation['charge_type'] }}</td>
                            <td class="px-4 py-3">{{ $allocation['period'] ?: 'N/A' }}</td>
                            <td class="px-4 py-3">{{ $allocation['charge_date'] ?: 'N/A' }}</td>
                            <td class="px-4 py-3 text-right font-medium">${{ number_format($allocation['amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-slate-500">Sin allocations registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Compartir recibo</h2>
        <p class="mt-1 text-sm text-slate-600">MVP: envío manual por email o WhatsApp.</p>

        <div class="mt-4 grid gap-3 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Correo destino</label>
                <input type="email" wire:model.blur="emailRecipient" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('emailRecipient') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                <button
                    type="button"
                    wire:click="sendEmail"
                    class="mt-3 rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
                >
                    Enviar por email
                </button>
                <p class="mt-2 text-xs text-slate-500">En desarrollo, revisa Mailpit para validar envío.</p>
            </div>
            <div>
                <p class="mb-1 text-sm font-medium text-slate-700">Link compartible (7 días)</p>
                <textarea readonly rows="4" class="w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-xs">{{ $shareUrl }}</textarea>
            </div>
        </div>
    </div>

    @if ($documents->isNotEmpty())
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
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
        </div>
    @endif
</section>
