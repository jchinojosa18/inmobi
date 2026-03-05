<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Cobranza</h1>
            <p class="mt-1 text-sm text-slate-600">Panel diario para seguimiento de contratos y cobranza.</p>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-6">
            <div class="md:col-span-2">
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Inquilino</label>
                <input
                    type="text"
                    wire:model.live.debounce.350ms="q"
                    placeholder="Nombre, email o teléfono"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                >
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Propiedad</label>
                <select wire:model.live="property_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Todas</option>
                    @foreach ($properties as $property)
                        <option value="{{ $property->id }}">{{ $property->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Unidad</label>
                <select wire:model.live="unit_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Todas</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}{{ $unit->code ? ' ('.$unit->code.')' : '' }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Días atraso mín</label>
                <input
                    type="number"
                    min="0"
                    wire:model.live="days_min"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                >
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Días atraso máx</label>
                <input
                    type="number"
                    min="0"
                    wire:model.live="days_max"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                >
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-4">
            <nav class="flex flex-wrap gap-2 py-3 text-sm">
                <button
                    type="button"
                    wire:click="$set('tab', 'overdue')"
                    class="rounded-md px-3 py-2 font-medium {{ $tab === 'overdue' ? 'bg-amber-100 text-amber-800' : 'text-slate-600 hover:bg-slate-100' }}"
                >
                    Vencidos
                </button>
                <button
                    type="button"
                    wire:click="$set('tab', 'grace')"
                    class="rounded-md px-3 py-2 font-medium {{ $tab === 'grace' ? 'bg-sky-100 text-sky-800' : 'text-slate-600 hover:bg-slate-100' }}"
                >
                    En gracia
                </button>
                <button
                    type="button"
                    wire:click="$set('tab', 'current')"
                    class="rounded-md px-3 py-2 font-medium {{ $tab === 'current' ? 'bg-emerald-100 text-emerald-800' : 'text-slate-600 hover:bg-slate-100' }}"
                >
                    Al corriente
                </button>
            </nav>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Contrato</th>
                        <th class="px-4 py-2">Inquilino</th>
                        <th class="px-4 py-2">Propiedad / Unidad</th>
                        <th class="px-4 py-2">Renta pendiente</th>
                        <th class="px-4 py-2 text-right">Días atraso</th>
                        <th class="px-4 py-2 text-right">Saldo pendiente</th>
                        <th class="px-4 py-2 text-right">Saldo a favor</th>
                        <th class="px-4 py-2 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($contracts as $row)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-4 py-2 text-slate-700">#{{ $row->contract_id }}</td>
                            <td class="px-4 py-2 text-slate-700">
                                {{ $row->tenant_name }}
                                <p class="text-xs text-slate-500">{{ $row->tenant_phone ?: ($row->tenant_email ?: 'Sin contacto') }}</p>
                            </td>
                            <td class="px-4 py-2 text-slate-700">{{ $row->property_name }} / {{ $row->unit_name ?? ($row->unit_code ?? '-') }}</td>
                            <td class="px-4 py-2 text-slate-700">
                                <p>{{ $row->overdue_period ?: 'Sin periodo' }}</p>
                                <p class="text-xs text-slate-500">
                                    Vence: {{ $row->due_date ? \Carbon\Carbon::parse($row->due_date)->format('Y-m-d') : 'N/D' }}
                                    · Gracia: {{ $row->grace_until ? \Carbon\Carbon::parse($row->grace_until)->format('Y-m-d') : 'N/D' }}
                                </p>
                            </td>
                            <td class="px-4 py-2 text-right font-medium {{ (int) $row->overdue_days > 0 ? 'text-amber-700' : 'text-slate-600' }}">
                                {{ (int) $row->overdue_days }}
                            </td>
                            <td class="px-4 py-2 text-right font-semibold text-slate-900">${{ number_format((float) $row->pending_balance, 2) }}</td>
                            <td class="px-4 py-2 text-right font-semibold {{ (float) $row->credit_balance > 0 ? 'text-emerald-700' : 'text-slate-500' }}">
                                ${{ number_format((float) $row->credit_balance, 2) }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                <div class="inline-flex flex-wrap justify-end gap-2">
                                    <button
                                        type="button"
                                        onclick="Livewire.dispatch('open-quick-payment', { contractId: {{ $row->contract_id }} })"
                                        class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                    >
                                        Registrar pago
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md border border-emerald-300 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50"
                                        x-data
                                        x-on:click="
                                            navigator.clipboard.writeText(@js($row->whatsapp_message));
                                            window.dispatchEvent(new CustomEvent('notify-copy'));
                                        "
                                    >
                                        Copiar mensaje WhatsApp
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">Sin contratos para los filtros seleccionados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-4 py-3">
            {{ $contracts->links() }}
        </div>
    </div>

    <div
        x-data="{ show: false }"
        x-on:notify-copy.window="show = true; setTimeout(() => show = false, 1400)"
        x-show="show"
        x-transition
        class="fixed bottom-4 right-4 rounded-md bg-slate-900 px-4 py-2 text-xs font-medium text-white shadow-lg"
        style="display: none;"
    >
        Mensaje copiado al portapapeles.
    </div>
</section>

