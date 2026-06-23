<section class="space-y-6">
    <x-ui.page-header
        title="Registrar pago"
        :description="'Contrato #'.$contract->id.' · '.$contract->tenant->full_name.' · '.$contract->unit->name"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('contracts.show', $contract) }}" variant="secondary">
                Volver al contrato
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card>
        <form wire:submit="save" class="grid gap-4 md:grid-cols-2" enctype="multipart/form-data">
            @error('month_close')
                <div class="md:col-span-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ $message }}
                </div>
            @enderror

            <div>
                <x-ui.input
                    id="payment-amount"
                    label="Monto *"
                    type="number"
                    step="0.01"
                    min="0.01"
                    wire:model.blur="amount"
                />
                @error('amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-ui.select id="payment-method" label="Método *" wire:model="method">
                    <option value="TRANSFER">Transferencia</option>
                    <option value="CASH">Efectivo</option>
                </x-ui.select>
                @error('method') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-ui.input
                    id="payment-paid-at"
                    label="Fecha y hora de pago *"
                    type="datetime-local"
                    wire:model.blur="paid_at"
                />
                @error('paid_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-ui.input
                    id="payment-reference"
                    label="Referencia"
                    type="text"
                    wire:model.blur="reference"
                />
                @error('reference') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <x-ui.input
                    id="payment-evidence"
                    label="Evidencia (opcional)"
                    type="file"
                    wire:model="evidence"
                    accept=".jpg,.jpeg,.png,.pdf"
                />
                @error('evidence') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-slate-500">Tipos permitidos: JPG, PNG, PDF. Máx: 5 MB.</p>
            </div>

            <div class="md:col-span-2 flex justify-end">
                <x-ui.button
                    type="submit"
                    wire:loading.attr="disabled"
                >
                    Guardar pago
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</section>
