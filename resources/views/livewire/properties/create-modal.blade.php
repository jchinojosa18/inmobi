<div>
    <x-ui.modal
        :open="$open"
        :title="$step === 'picker' ? 'Nuevo inmueble' : 'Nuevo ' . strtolower($selectedTypeLabel)"
        :aria-label="$step === 'picker' ? 'Nuevo inmueble' : 'Nuevo ' . strtolower($selectedTypeLabel)"
        max-width="2xl"
        close-action="cancelForm"
    >
        @if ($step === 'picker')
            <p class="mb-4 text-sm text-slate-600">
                Selecciona el tipo de inmueble que deseas registrar.
            </p>

            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($typeOptions as $option)
                    <button
                        type="button"
                        wire:click="selectType('{{ $option['key'] }}')"
                        class="rounded-xl border border-slate-200 bg-white p-4 text-left transition hover:border-slate-400 hover:bg-slate-50"
                    >
                        <p class="font-medium text-slate-900">{{ $option['label'] }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $option['description'] }}</p>
                    </button>
                @endforeach
            </div>
        @else
            <div class="mb-4 flex items-center gap-2">
                <button
                    type="button"
                    wire:click="backToPicker"
                    class="rounded-md border border-slate-300 px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50"
                >
                    ← Cambiar tipo
                </button>
                <span class="text-xs text-slate-500">{{ $selectedTypeLabel }}</span>
            </div>

            @if ($selectedType === \App\Livewire\Properties\CreateModal::TYPE_BUILDING)
                <p class="mb-4 text-sm text-slate-600">
                    Captura los datos del edificio. Después podrás generar las unidades por piso.
                </p>

                <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Nombre *</label>
                        <input type="text" wire:model.live="name" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm uppercase">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Código *</label>
                        <input type="text" wire:model.live="code" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm uppercase">
                        @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Estado *</label>
                        <select wire:model="formStatus" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                        @error('formStatus') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Dirección</label>
                        <input type="text" wire:model.live="address" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm uppercase">
                        @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Notas</label>
                        <textarea wire:model.blur="notes" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                        @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2 flex flex-wrap items-center justify-end gap-2">
                        <button type="button" wire:click="cancelForm" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Cancelar
                        </button>
                        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                            Crear edificio
                        </button>
                    </div>
                </form>
            @else
                <p class="mb-4 text-sm text-slate-600">
                    Captura el inmueble en un solo paso. El sistema crea la propiedad y su unidad automáticamente.
                </p>

                <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Nombre *</label>
                        <input type="text" wire:model.live="name" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm uppercase">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Dirección</label>
                        <input type="text" wire:model.live="address" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm uppercase">
                        @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Notas</label>
                        <textarea wire:model.blur="notes" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                        @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2 flex flex-wrap items-center justify-end gap-2">
                        <button type="button" wire:click="cancelForm" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Cancelar
                        </button>
                        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                            Crear {{ strtolower($selectedTypeLabel) }}
                        </button>
                    </div>
                </form>
            @endif
        @endif
    </x-ui.modal>
</div>
