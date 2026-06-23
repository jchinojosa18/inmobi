<section class="space-y-6">
    <x-ui.page-header
        title="Configuración"
        description="Parámetros operativos por organización."
    />

    @unless ($canManageSettings)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            No tienes permisos para editar esta sección.
        </div>
    @endunless

    <div class="grid gap-3 md:grid-cols-3">
        <a href="{{ route('settings.roles') }}" class="block rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm transition hover:border-slate-300 hover:shadow">
            <p class="text-sm font-semibold text-slate-900">Roles y permisos</p>
            <p class="mt-1 text-xs text-slate-600">Vista de solo lectura por rol (Admin, Capturista y Lectura).</p>
        </a>
        @can('invitations.manage')
            <a href="{{ route('settings.invitations.index') }}" class="block rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm transition hover:border-slate-300 hover:shadow">
                <p class="text-sm font-semibold text-slate-900">Invitaciones</p>
                <p class="mt-1 text-xs text-slate-600">Administra invitaciones para sumar usuarios a tu organización.</p>
            </a>
        @endcan
        @can('plazas.manage')
            <a href="{{ route('settings.plazas.index') }}" class="block rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm transition hover:border-slate-300 hover:shadow">
                <p class="text-sm font-semibold text-slate-900">Plazas</p>
                <p class="mt-1 text-xs text-slate-600">Gestiona plazas y configuración multi-ciudad.</p>
            </a>
        @endcan
    </div>

    <x-ui.card>
        <h2 class="text-lg font-semibold text-slate-900">Folios de recibo</h2>
        <p class="mt-1 text-sm text-slate-600">Configuración usada al generar `receipt_folio` por organización.</p>

        <form wire:submit="saveSettings" class="mt-4 grid gap-4 md:grid-cols-3">
            <div>
                <x-ui.select label="Modo" wire:model="receiptFolioMode" :disabled="! $canManageSettings">
                    <option value="annual">Anual (reinicia por año)</option>
                    <option value="continuous">Continuo</option>
                </x-ui.select>
                @error('receiptFolioMode') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-ui.input
                    label="Prefijo (opcional)"
                    type="text"
                    wire:model.blur="receiptFolioPrefix"
                    placeholder="REC"
                    :disabled="! $canManageSettings"
                />
                @error('receiptFolioPrefix') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-ui.input
                    label="Padding"
                    type="number"
                    min="3"
                    max="10"
                    wire:model.blur="receiptFolioPadding"
                    :disabled="! $canManageSettings"
                />
                @error('receiptFolioPadding') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-3">
                <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500">Plantilla WhatsApp</label>
                <textarea wire:model.blur="whatsAppTemplate" rows="4" @disabled(! $canManageSettings) class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100"></textarea>
                @error('whatsAppTemplate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-3">
                <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500">Plantilla email</label>
                <textarea wire:model.blur="emailTemplate" rows="6" @disabled(! $canManageSettings) class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100"></textarea>
                @error('emailTemplate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-3 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                Variables disponibles:
                {{ collect($templateVariables)->map(fn ($var) => '{'.$var.'}')->join(', ') }}
            </div>

            @if ($canManageSettings)
                <div class="md:col-span-3 flex justify-end">
                    <x-ui.button type="submit">
                        Guardar configuración
                    </x-ui.button>
                </div>
            @endif
        </form>
    </x-ui.card>

    <x-ui.card>
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
    </x-ui.card>

    <x-ui.card :padding="false">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Categorías de egresos</h2>
            @if ($canManageExpenseCategories)
                <form wire:submit="createExpenseCategory" class="flex flex-wrap items-center gap-2">
                    <x-ui.input
                        type="text"
                        wire:model.blur="newExpenseCategory"
                        placeholder="Nueva categoría"
                        class="w-56"
                    />
                    <x-ui.button type="submit" size="sm">
                        Agregar
                    </x-ui.button>
                </form>
            @endif
        </div>
        @error('newExpenseCategory') <p class="px-5 pt-2 text-sm text-red-600">{{ $message }}</p> @enderror

        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-3">Categoría</th>
                <th class="px-4 py-3 text-right">Acciones</th>
            </x-slot:head>
            <x-slot:body>
                @forelse ($categories as $category)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-800">
                            @if ($editingExpenseCategoryId === $category->id)
                                <x-ui.input type="text" wire:model.blur="editingExpenseCategoryName" />
                                @error('editingExpenseCategoryName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            @else
                                {{ $category->name }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($canManageExpenseCategories)
                                <div class="inline-flex items-center gap-2">
                                    @if ($editingExpenseCategoryId === $category->id)
                                        <x-ui.button type="button" wire:click="updateExpenseCategory" variant="secondary" size="sm">
                                            Guardar
                                        </x-ui.button>
                                        <x-ui.button type="button" wire:click="cancelEditingExpenseCategory" variant="secondary" size="sm">
                                            Cancelar
                                        </x-ui.button>
                                    @else
                                        <x-ui.button type="button" wire:click="startEditingExpenseCategory({{ $category->id }})" variant="secondary" size="sm">
                                            Editar
                                        </x-ui.button>
                                        <x-ui.button type="button" wire:click="confirmDeleteExpenseCategory({{ $category->id }})" variant="danger" size="sm">
                                            Eliminar
                                        </x-ui.button>
                                    @endif
                                </div>
                            @else
                                <span class="text-xs text-slate-500">Solo lectura</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-state title="Sin categorías configuradas." :colspan="2" />
                @endforelse
            </x-slot:body>
        </x-ui.table>
    </x-ui.card>

    @if ($canManageExpenseCategories)
        <x-ui.confirm-modal
            :open="$showDeleteConfirm"
            title="Eliminar categoría"
            confirm-action="executeDeleteConfirm"
            cancel-action="cancelDeleteConfirm"
            confirm-label="Eliminar categoría"
            aria-label="Confirmar eliminación de categoría"
        >
            <p class="text-slate-700">
                Vas a eliminar la categoría <span class="font-semibold text-slate-900">{{ $pendingDeleteCategoryName }}</span>.
            </p>
            <p class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                Los egresos ya registrados con esta categoría no se modifican.
            </p>
        </x-ui.confirm-modal>
    @endif
</section>
