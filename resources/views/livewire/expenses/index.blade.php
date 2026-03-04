<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Egresos</h1>
            <p class="mt-1 text-sm text-slate-600">Registro y control de gastos operativos.</p>
        </div>
        <button
            type="button"
            wire:click="startCreate"
            class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
        >
            Registrar egreso
        </button>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-4">
            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Desde</label>
                <input type="date" wire:model.live="dateFromFilter" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Hasta</label>
                <input type="date" wire:model.live="dateToFilter" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Unidad</label>
                <select wire:model.live="unitFilter" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Todas</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Categoría</label>
                <select wire:model.live="categoryFilter" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Todas</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}">{{ $category }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    @if ($showForm)
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Nuevo egreso</h2>
            <form wire:submit="save" class="mt-4 grid gap-4 md:grid-cols-2">
                @error('month_close')
                    <div class="md:col-span-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                        {{ $message }}
                    </div>
                @enderror

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Categoría *</label>
                    <input type="text" list="expense-categories-list" wire:model.blur="category" placeholder="MANTENIMIENTO, LIMPIEZA, SERVICIO..." class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <datalist id="expense-categories-list">
                        @foreach ($categories as $category)
                            <option value="{{ $category }}"></option>
                        @endforeach
                    </datalist>
                    @error('category') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Monto *</label>
                    <input type="number" step="0.01" min="0.01" wire:model.blur="amount" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Fecha *</label>
                    <input type="date" wire:model.blur="spent_at" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('spent_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Unidad</label>
                    <select wire:model="unit_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Gasto general</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                    @error('unit_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Proveedor</label>
                    <input type="text" wire:model.blur="vendor" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('vendor') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Notas</label>
                    <textarea wire:model.blur="notes" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                    @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2 flex justify-end gap-2">
                    <button type="button" wire:click="cancelForm" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Cancelar
                    </button>
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Categoría</th>
                        <th class="px-4 py-3">Unidad</th>
                        <th class="px-4 py-3">Proveedor</th>
                        <th class="px-4 py-3 text-right">Monto</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($expenses as $expense)
                        <tr>
                            <td class="px-4 py-3">{{ optional($expense->spent_at)->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $expense->category }}</td>
                            <td class="px-4 py-3 text-slate-700">
                                {{ $expense->unit?->name ?: 'General' }}
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $expense->vendor ?: 'N/A' }}</td>
                            <td class="px-4 py-3 text-right font-medium">${{ number_format((float) $expense->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-slate-500">No hay egresos en los filtros seleccionados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 bg-slate-50 px-4 py-3">
            {{ $expenses->links() }}
        </div>
    </div>
</section>
