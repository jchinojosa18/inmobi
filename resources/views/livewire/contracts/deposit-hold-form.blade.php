<x-ui.card>
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
            <x-ui.input label="Fecha recepción *" type="date" wire:model.blur="deposit_received_at" />
            @error('deposit_received_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <x-ui.input label="Monto *" type="number" step="0.01" min="0.01" wire:model.blur="deposit_amount" />
            @error('deposit_amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <x-ui.input label="Notas" type="text" wire:model.blur="deposit_notes" />
            @error('deposit_notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="md:col-span-3 flex justify-end">
            <x-ui.button type="submit">
                Registrar depósito
            </x-ui.button>
        </div>
    </form>
</x-ui.card>
