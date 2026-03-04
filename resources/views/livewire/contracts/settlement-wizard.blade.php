<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Finiquito de contrato</h2>
            <p class="mt-1 text-sm text-slate-600">
                Registra cargos de salida, aplica depósito y cierra contrato.
            </p>
        </div>
        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700">
            <p>Depósito pagado: <strong>${{ number_format($paidDeposit, 2) }}</strong></p>
            <p>Depósito aplicado: <strong>${{ number_format($appliedDeposit, 2) }}</strong></p>
            <p>Depósito devuelto: <strong>${{ number_format($refundedDeposit, 2) }}</strong></p>
            <p>Disponible: <strong>${{ number_format($availableDeposit, 2) }}</strong></p>
            <p>Adeudo actual: <strong>${{ number_format($currentOutstanding, 2) }}</strong></p>
        </div>
    </div>

    <form wire:submit="process" class="mt-4 space-y-4" enctype="multipart/form-data">
        @error('settlement_general')
            <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ $message }}
            </div>
        @enderror

        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Fecha salida *</label>
                <input type="date" wire:model.blur="move_out_date" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('move_out_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-700">Conceptos de salida</h3>
                <button type="button" wire:click="addConcept" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                    Agregar concepto
                </button>
            </div>

            @error('concepts') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            @foreach ($concepts as $index => $concept)
                <div class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3 md:grid-cols-12">
                    <div class="md:col-span-5">
                        <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Concepto *</label>
                        <input type="text" wire:model.blur="concepts.{{ $index }}.description" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('concepts.'.$index.'.description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-3">
                        <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Monto *</label>
                        <input type="number" step="0.01" min="0.01" wire:model.blur="concepts.{{ $index }}.amount" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('concepts.'.$index.'.amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-3">
                        <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Evidencia (foto)</label>
                        <input type="file" wire:model="evidenceFiles.{{ $index }}" accept=".jpg,.jpeg,.png,.webp" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                        @error('evidenceFiles.'.$index) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-end justify-end md:col-span-1">
                        <button type="button" wire:click="removeConcept({{ $index }})" class="rounded-md border border-red-200 px-3 py-2 text-xs font-medium text-red-700 hover:bg-red-50">
                            Quitar
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex justify-end">
            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Confirmar finiquito
            </button>
        </div>
    </form>

    @if ($lastSettlementPdfUrl)
        <div class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <p>{{ $lastSettlementSummary }}</p>
            <a href="{{ $lastSettlementPdfUrl }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex rounded-md bg-emerald-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-600">
                Ver PDF de finiquito
            </a>
        </div>
    @endif
</div>
