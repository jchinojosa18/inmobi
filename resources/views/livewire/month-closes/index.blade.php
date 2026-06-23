<section class="space-y-6">
    <x-ui.page-header
        title="Cierres mensuales"
        description="Congela números por mes y bloquea modificaciones retroactivas."
    />

    <x-ui.card>
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-700">Cerrar mes</h2>
        <form wire:submit="closeMonth" class="mt-3 grid gap-3 md:grid-cols-4">
            <div>
                <x-ui.input
                    id="month-to-close"
                    label="Mes (YYYY-MM)"
                    type="month"
                    wire:model="monthToClose"
                    :disabled="! $canCloseMonth"
                />
                @error('monthToClose') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="md:col-span-2">
                <x-ui.input
                    id="month-close-notes"
                    label="Notas (opcional)"
                    type="text"
                    wire:model.blur="notes"
                    maxlength="500"
                    placeholder="Comentario del cierre"
                    :disabled="! $canCloseMonth"
                />
            </div>
            <div class="flex items-end justify-end">
                @if ($canCloseMonth)
                    <x-ui.button type="submit">
                        Cerrar mes
                    </x-ui.button>
                @else
                    <span class="text-xs text-slate-500">Sin permiso para cerrar</span>
                @endif
            </div>
        </form>
    </x-ui.card>

    <x-ui.table>
        <x-slot:head>
            <th class="px-4 py-3">Mes</th>
            <th class="px-4 py-3">Estado</th>
            <th class="px-4 py-3">Cerrado por</th>
            <th class="px-4 py-3">Fecha cierre</th>
            <th class="px-4 py-3 text-right">Acciones</th>
        </x-slot:head>
        <x-slot:body>
            @foreach ($rows as $row)
                <tr class="transition hover:bg-slate-50/80">
                    <td class="px-4 py-3 font-medium text-slate-900">{{ $row['month'] }}</td>
                    <td class="px-4 py-3">
                        @if ($row['is_closed'])
                            <x-ui.badge variant="danger">Cerrado</x-ui.badge>
                        @else
                            <x-ui.badge variant="success">Abierto</x-ui.badge>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-700">{{ $row['closed_by'] ?? '-' }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ optional($row['closed_at'])->format('Y-m-d H:i') ?? '-' }}</td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end gap-2">
                            @if (! $row['is_closed'])
                                <x-ui.button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    wire:click="closeMonth('{{ $row['month'] }}')"
                                >
                                    Cerrar mes
                                </x-ui.button>
                            @elseif ($canReopenMonth)
                                <x-ui.button
                                    type="button"
                                    variant="danger"
                                    size="sm"
                                    wire:click="reopenMonth('{{ $row['month'] }}')"
                                >
                                    Reabrir mes
                                </x-ui.button>
                            @else
                                <span class="text-xs text-slate-500">Sin permiso para reabrir</span>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-slot:body>
    </x-ui.table>
</section>
