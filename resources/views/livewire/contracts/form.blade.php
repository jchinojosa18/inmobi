<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">
                {{ $isEdit ? 'Editar contrato' : 'Nuevo contrato' }}
            </h1>
            <p class="mt-1 text-sm text-slate-600">
                Registro base del contrato entre unidad e inquilino.
            </p>
        </div>
        @if ($isEdit)
            <a
                href="{{ route('contracts.show', $contractId) }}"
                class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                Ver detalle
            </a>
        @endif
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Unidad *</label>
                <select wire:model="unit_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Seleccionar unidad</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">
                            {{ $unit->property?->name }} — {{ $unit->name }}@if($unit->code) ({{ $unit->code }}) @endif
                        </option>
                    @endforeach
                </select>
                @error('unit_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Inquilino *</label>
                <select wire:model="tenant_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Seleccionar inquilino</option>
                    @foreach ($tenants as $tenant)
                        <option value="{{ $tenant->id }}">{{ $tenant->full_name }}</option>
                    @endforeach
                </select>
                @error('tenant_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Renta mensual *</label>
                <input type="number" step="0.01" min="0" wire:model.blur="rent_amount" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('rent_amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Depósito *</label>
                <input type="number" step="0.01" min="0" wire:model.blur="deposit_amount" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('deposit_amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Día de vencimiento *</label>
                <input type="number" min="1" max="31" wire:model.blur="due_day" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('due_day') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Días de gracia *</label>
                <input type="number" min="0" max="31" wire:model.blur="grace_days" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('grace_days') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tasa multa diaria (%) *</label>
                <input type="number" step="0.0001" min="0" wire:model.blur="penalty_rate_daily" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('penalty_rate_daily') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Estado *</label>
                <select wire:model="status" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="active">Activo</option>
                    <option value="ended">Finalizado</option>
                </select>
                @error('status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Inicio *</label>
                <input type="date" wire:model.blur="starts_at" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('starts_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Fin</label>
                <input type="date" wire:model.blur="ends_at" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('ends_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Notas</label>
                <textarea wire:model.blur="meta_notes" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                @error('meta_notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2 flex justify-end">
                <button
                    type="submit"
                    class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
                >
                    Guardar contrato
                </button>
            </div>
        </form>
    </div>
</section>
