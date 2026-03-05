<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Cierres mensuales</h1>
            <p class="mt-1 text-sm text-slate-600">Congela números por mes y bloquea modificaciones retroactivas.</p>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-700">Cerrar mes</h2>
        <form wire:submit="closeMonth" class="mt-3 grid gap-3 md:grid-cols-4">
            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Mes (YYYY-MM)</label>
                <input
                    type="month"
                    wire:model="monthToClose"
                    @disabled(! $canCloseMonth)
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100"
                >
                @error('monthToClose') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="md:col-span-2">
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Notas (opcional)</label>
                <input
                    type="text"
                    wire:model.blur="notes"
                    maxlength="500"
                    @disabled(! $canCloseMonth)
                    placeholder="Comentario del cierre"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100"
                >
            </div>
            <div class="flex items-end justify-end">
                @if ($canCloseMonth)
                    <button
                        type="submit"
                        class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
                    >
                        Cerrar mes
                    </button>
                @else
                    <span class="text-xs text-slate-500">Sin permiso para cerrar</span>
                @endif
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Mes</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Cerrado por</th>
                        <th class="px-4 py-3">Fecha cierre</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @foreach ($rows as $row)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $row['month'] }}</td>
                            <td class="px-4 py-3">
                                @if ($row['is_closed'])
                                    <span class="inline-flex rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-700">Cerrado</span>
                                @else
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-700">Abierto</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $row['closed_by'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ optional($row['closed_at'])->format('Y-m-d H:i') ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    @if (! $row['is_closed'])
                                        <button
                                            type="button"
                                            wire:click="closeMonth('{{ $row['month'] }}')"
                                            class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                        >
                                            Cerrar mes
                                        </button>
                                    @elseif ($canReopenMonth)
                                        <button
                                            type="button"
                                            wire:click="reopenMonth('{{ $row['month'] }}')"
                                            class="rounded-md border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50"
                                        >
                                            Reabrir mes
                                        </button>
                                    @else
                                        <span class="text-xs text-slate-500">Sin permiso para reabrir</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
