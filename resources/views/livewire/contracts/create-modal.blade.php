<div>
    <x-ui.modal
        :open="$open"
        title="Nuevo contrato"
        aria-label="Nuevo contrato"
        max-width="2xl"
    >
        <p class="mb-4 text-sm text-slate-600">
            Registro base del contrato entre unidad e inquilino.
        </p>

        <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
            <div>
                <x-ui.select label="Unidad *" wire:model="unit_id">
                    <option value="">Seleccionar unidad</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">
                            {{ $unit->property?->name }} — {{ $unit->name }}@if($unit->code) ({{ $unit->code }}) @endif
                        </option>
                    @endforeach
                </x-ui.select>
                @error('unit_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-ui.select label="Inquilino *" wire:model="tenant_id">
                    <option value="">Seleccionar inquilino</option>
                    @foreach ($tenants as $tenant)
                        <option value="{{ $tenant->id }}">{{ $tenant->full_name }}</option>
                    @endforeach
                </x-ui.select>
                @error('tenant_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-ui.input label="Renta mensual *" type="number" step="0.01" min="0" wire:model.blur="rent_amount" />
                @error('rent_amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-ui.input label="Depósito *" type="number" step="0.01" min="0" wire:model.blur="deposit_amount" />
                @error('deposit_amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-ui.input label="Día de vencimiento *" type="number" min="1" max="31" wire:model.blur="due_day" />
                @error('due_day') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-ui.input label="Días de gracia *" type="number" min="0" max="31" wire:model.blur="grace_days" />
                @error('grace_days') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-ui.input label="Tasa multa diaria (%) *" type="number" step="0.0001" min="0.01" max="100" wire:model.blur="penalty_rate_daily" />
                @error('penalty_rate_daily') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-ui.select label="Estado *" wire:model="status">
                    <option value="active">Activo</option>
                    <option value="ended">Finalizado</option>
                </x-ui.select>
                @error('status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-ui.input label="Inicio *" type="date" wire:model.blur="starts_at" />
                @error('starts_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-ui.input label="Fin" type="date" wire:model.blur="ends_at" />
                @error('ends_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500">Notas</label>
                <textarea wire:model.blur="meta_notes" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea>
                @error('meta_notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2 flex flex-wrap items-center justify-end gap-2">
                <x-ui.button type="button" wire:click="cancelForm" variant="secondary">
                    Cancelar
                </x-ui.button>
                <x-ui.button type="submit">
                    Guardar contrato
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
