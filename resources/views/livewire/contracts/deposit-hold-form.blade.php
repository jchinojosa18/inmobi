<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="text-lg font-semibold text-slate-900">Depósito recibido</h2>
    <p class="mt-1 text-sm text-slate-600">
        Registra el depósito como cargo <code>DEPOSIT_HOLD</code>. No se considera ingreso operativo.
    </p>

    <form wire:submit="registerDeposit" class="mt-4 grid gap-4 md:grid-cols-3">
        @error('deposit_general')
            <div class="md:col-span-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ $message }}
            </div>
        @enderror

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Fecha recepción *</label>
            <input type="date" wire:model.blur="deposit_received_at" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            @error('deposit_received_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Monto *</label>
            <input type="number" step="0.01" min="0.01" wire:model.blur="deposit_amount" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            @error('deposit_amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Notas</label>
            <input type="text" wire:model.blur="deposit_notes" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            @error('deposit_notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="md:col-span-3 flex justify-end">
            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Registrar depósito
            </button>
        </div>
    </form>
</div>
