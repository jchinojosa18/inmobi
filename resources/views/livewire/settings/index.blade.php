<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Configuración</h1>
            <p class="mt-1 text-sm text-slate-600">Parámetros operativos por organización.</p>
        </div>
    </div>

    @unless ($isAdmin)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Solo usuarios con rol Admin pueden editar esta sección.
        </div>
    @endunless

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Folios de recibo</h2>
        <p class="mt-1 text-sm text-slate-600">Configuración usada al generar `receipt_folio` por organización.</p>

        <form wire:submit="saveSettings" class="mt-4 grid gap-4 md:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Modo</label>
                <select wire:model="receiptFolioMode" @disabled(! $isAdmin) class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100">
                    <option value="annual">Anual (reinicia por año)</option>
                    <option value="continuous">Continuo</option>
                </select>
                @error('receiptFolioMode') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Prefijo (opcional)</label>
                <input type="text" wire:model.blur="receiptFolioPrefix" @disabled(! $isAdmin) placeholder="REC" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100">
                @error('receiptFolioPrefix') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Padding</label>
                <input type="number" min="3" max="10" wire:model.blur="receiptFolioPadding" @disabled(! $isAdmin) class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100">
                @error('receiptFolioPadding') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-3">
                <label class="mb-1 block text-sm font-medium text-slate-700">Plantilla WhatsApp</label>
                <textarea wire:model.blur="whatsAppTemplate" rows="4" @disabled(! $isAdmin) class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100"></textarea>
                @error('whatsAppTemplate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-3">
                <label class="mb-1 block text-sm font-medium text-slate-700">Plantilla email</label>
                <textarea wire:model.blur="emailTemplate" rows="6" @disabled(! $isAdmin) class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100"></textarea>
                @error('emailTemplate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-3 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                Variables disponibles:
                {{ collect($templateVariables)->map(fn ($var) => '{'.$var.'}')->join(', ') }}
            </div>

            @if ($isAdmin)
                <div class="md:col-span-3 flex justify-end">
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        Guardar configuración
                    </button>
                </div>
            @endif
        </form>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Política de multas (documentación)</h2>
        <p class="mt-1 text-sm text-slate-600">Esta sección es informativa. No modifica el algoritmo actual.</p>

        <dl class="mt-4 grid gap-4 md:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Redondeo</dt>
                <dd class="mt-1 text-sm text-slate-800">Siempre {{ $penaltyRoundingScale }} decimales.</dd>
            </div>
            <div class="md:col-span-2">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Policy actual</dt>
                <dd class="mt-1 text-sm text-slate-800">{{ $penaltyPolicy }}</dd>
            </div>
        </dl>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-slate-900">Categorías de egresos</h2>
            @if ($isAdmin)
                <form wire:submit="createExpenseCategory" class="flex flex-wrap items-center gap-2">
                    <input
                        type="text"
                        wire:model.blur="newExpenseCategory"
                        placeholder="Nueva categoría"
                        class="w-56 rounded-md border border-slate-300 px-3 py-2 text-sm"
                    >
                    <button type="submit" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        Agregar
                    </button>
                </form>
            @endif
        </div>
        @error('newExpenseCategory') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Categoría</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($categories as $category)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-800">
                                @if ($editingExpenseCategoryId === $category->id)
                                    <input type="text" wire:model.blur="editingExpenseCategoryName" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                    @error('editingExpenseCategoryName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                @else
                                    {{ $category->name }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if ($isAdmin)
                                    <div class="inline-flex items-center gap-2">
                                        @if ($editingExpenseCategoryId === $category->id)
                                            <button type="button" wire:click="updateExpenseCategory" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                                Guardar
                                            </button>
                                            <button type="button" wire:click="cancelEditingExpenseCategory" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                                Cancelar
                                            </button>
                                        @else
                                            <button type="button" wire:click="startEditingExpenseCategory({{ $category->id }})" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                                Editar
                                            </button>
                                            <button type="button" wire:click="deleteExpenseCategory({{ $category->id }})" wire:confirm="¿Eliminar categoría?" class="rounded-md border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">
                                                Eliminar
                                            </button>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-xs text-slate-500">Solo lectura</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-4 py-6 text-center text-slate-500">Sin categorías configuradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
