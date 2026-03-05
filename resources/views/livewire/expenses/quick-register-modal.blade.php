<div>
    @if($open)
    <div
        id="quick-expense-modal"
        role="dialog"
        aria-modal="true"
        aria-label="Registrar egreso"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
    >
        {{-- Overlay --}}
        <div
            class="absolute inset-0 bg-black/50"
            wire:click="close"
            aria-hidden="true"
        ></div>

        {{-- Card --}}
        <div class="relative z-10 w-full max-w-xl rounded-2xl bg-white shadow-xl">

            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <h2 class="text-base font-semibold text-slate-900">Registrar egreso</h2>
                <button
                    type="button"
                    wire:click="close"
                    class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                    aria-label="Cerrar"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="max-h-[80vh] overflow-y-auto px-5 py-4">
                <div class="space-y-4">

                    {{-- Month close error --}}
                    @error('month_close')
                        <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                            {{ $message }}
                        </div>
                    @enderror

                    {{-- Date + Amount --}}
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-700">Fecha *</label>
                            <input
                                type="date"
                                wire:model.blur="spentAt"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
                            >
                            @error('spentAt') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-700">Monto *</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                wire:model.blur="amount"
                                placeholder="0.00"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
                            >
                            @error('amount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Category --}}
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Categoría *</label>
                        <input
                            id="qem-category"
                            type="text"
                            list="qem-categories-list"
                            wire:model.blur="category"
                            placeholder="MANTENIMIENTO, LIMPIEZA, SERVICIO…"
                            autocomplete="off"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
                        >
                        <datalist id="qem-categories-list">
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}"></option>
                            @endforeach
                        </datalist>
                        @error('category') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Scope: general vs unit --}}
                    <div>
                        <label class="mb-2 block text-xs font-medium text-slate-700">Asignación</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                                <input type="radio" wire:model.live="scope" value="general" class="accent-slate-700">
                                Gasto general
                            </label>
                            <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                                <input type="radio" wire:model.live="scope" value="unit" class="accent-slate-700">
                                Asignar a unidad
                            </label>
                        </div>
                    </div>

                    {{-- Unit typeahead (only when scope = 'unit') --}}
                    @if($scope === 'unit')
                    <div x-data>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Unidad *</label>
                        <div class="relative">
                            <input
                                id="qem-unit-input"
                                type="text"
                                wire:model.live.debounce.200ms="unitQuery"
                                wire:keydown.escape="$set('unitResults', [])"
                                placeholder="Buscar por propiedad, nombre o código…"
                                autocomplete="off"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
                            >

                            @if(count($unitResults) > 0)
                            <ul class="absolute left-0 right-0 top-full z-20 mt-1 max-h-48 overflow-y-auto divide-y divide-slate-100 rounded-md border border-slate-200 bg-white shadow-md">
                                @foreach($unitResults as $result)
                                <li>
                                    <button
                                        type="button"
                                        wire:click="selectUnit({{ $result['id'] }})"
                                        class="w-full px-3 py-2 text-left text-sm text-slate-800 hover:bg-slate-50"
                                    >
                                        {{ $result['label'] }}
                                    </button>
                                </li>
                                @endforeach
                            </ul>
                            @endif
                        </div>

                        @if($unitId)
                            <p class="mt-1 text-xs text-emerald-700">
                                Unidad seleccionada (ID: {{ $unitId }})
                            </p>
                        @endif
                        @error('unitId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    @endif

                    {{-- Vendor + Notes --}}
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-700">
                                Proveedor <span class="text-slate-400">(opcional)</span>
                            </label>
                            <input
                                type="text"
                                wire:model.blur="vendor"
                                maxlength="150"
                                placeholder="Nombre del proveedor"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
                            >
                            @error('vendor') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-700">
                                Notas <span class="text-slate-400">(opcional)</span>
                            </label>
                            <input
                                type="text"
                                wire:model.blur="notes"
                                maxlength="1000"
                                placeholder="Descripción breve"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
                            >
                            @error('notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Evidence --}}
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">
                            Comprobante <span class="text-slate-400">(JPG, PNG, PDF — máx. 5 MB, opcional)</span>
                        </label>
                        <input
                            type="file"
                            wire:model="evidenceFile"
                            accept=".jpg,.jpeg,.png,.pdf"
                            class="w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-slate-700 hover:file:bg-slate-200"
                        >
                        @error('evidenceFile') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                </div>
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-5 py-4">
                <button
                    type="button"
                    wire:click="close"
                    class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    wire:click="save"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-60 cursor-not-allowed"
                    class="rounded-md bg-slate-900 px-5 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="save">Guardar egreso</span>
                    <span wire:loading wire:target="save">Guardando…</span>
                </button>
            </div>

        </div>
    </div>
    @endif
</div>
