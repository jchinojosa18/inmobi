<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Registrar pago</h1>
            <p class="mt-1 text-sm text-slate-600">
                Contrato #{{ $contract->id }} · {{ $contract->tenant->full_name }} · {{ $contract->unit->name }}
            </p>
        </div>
        <a
            href="{{ route('contracts.show', $contract) }}"
            class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >
            Volver al contrato
        </a>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <form wire:submit="save" class="grid gap-4 md:grid-cols-2" enctype="multipart/form-data">
            @error('month_close')
                <div class="md:col-span-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ $message }}
                </div>
            @enderror

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Monto *</label>
                <input type="number" step="0.01" min="0.01" wire:model.blur="amount" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Método *</label>
                <select wire:model="method" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="TRANSFER">Transferencia</option>
                    <option value="CASH">Efectivo</option>
                </select>
                @error('method') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Fecha y hora de pago *</label>
                <input type="datetime-local" wire:model.blur="paid_at" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('paid_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Referencia</label>
                <input type="text" wire:model.blur="reference" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('reference') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Evidencia (opcional)</label>
                <input type="file" wire:model="evidence" accept=".jpg,.jpeg,.png,.pdf" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('evidence') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-slate-500">Tipos permitidos: JPG, PNG, PDF. Máx: 5 MB.</p>
            </div>

            <div class="md:col-span-2 flex justify-end">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60"
                >
                    Guardar pago
                </button>
            </div>
        </form>
    </div>
</section>
