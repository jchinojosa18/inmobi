<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Auditoría</h1>
            <p class="mt-1 text-sm text-slate-600">Historial de acciones de negocio registradas en el sistema.</p>
        </div>
        <a
            href="{{ route('settings.audit.export') }}?{{ http_build_query(['date_from' => $dateFrom, 'date_to' => $dateTo, 'actor_user_id' => $actorUserId, 'action' => $action, 'search' => $search]) }}"
            class="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >
            Exportar CSV
        </a>
    </div>

    {{-- Filters --}}
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Desde</label>
                <input type="date" wire:model.live="dateFrom" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Hasta</label>
                <input type="date" wire:model.live="dateTo" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Usuario</label>
                <select wire:model.live="actorUserId" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                    <option value="">Todos</option>
                    @foreach ($actors as $actor)
                        <option value="{{ $actor->id }}">{{ $actor->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Acción</label>
                <select wire:model.live="action" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                    <option value="">Todas</option>
                    @foreach ($actions as $act)
                        <option value="{{ $act }}">{{ $act }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Buscar en resumen</label>
                <input type="text" wire:model.live.debounce.400ms="search" placeholder="Buscar..." class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Entidad</label>
                <select wire:model.live="auditableType" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                    <option value="">Todas</option>
                    @foreach ($auditableTypes as $fullClass => $label)
                        <option value="{{ $fullClass }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2 whitespace-nowrap">Fecha/hora</th>
                        <th class="px-4 py-2">Usuario</th>
                        <th class="px-4 py-2">Acción</th>
                        <th class="px-4 py-2">Resumen</th>
                        <th class="px-4 py-2">Entidad</th>
                        <th class="px-4 py-2 text-right">Ver</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($events as $event)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-2 whitespace-nowrap text-xs text-slate-500">
                                {{ $event->occurred_at->timezone('America/Tijuana')->format('Y-m-d H:i') }}
                            </td>
                            <td class="px-4 py-2">
                                @if ($event->actor)
                                    <span class="font-medium">{{ $event->actor->name }}</span>
                                    <span class="block text-xs text-slate-400">{{ $event->actor->email }}</span>
                                @else
                                    <span class="text-slate-400">Sistema</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-mono font-medium text-slate-700">
                                    {{ $event->action }}
                                </span>
                            </td>
                            <td class="px-4 py-2 max-w-xs truncate">{{ $event->summary }}</td>
                            <td class="px-4 py-2 text-xs text-slate-500">
                                @if ($event->auditable_type)
                                    {{ class_basename($event->auditable_type) }} #{{ $event->auditable_id }}
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right">
                                <button
                                    type="button"
                                    wire:click="viewEvent({{ $event->id }})"
                                    class="rounded border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100"
                                >
                                    Ver
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                                Sin eventos para los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($events->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">
                {{ $events->links() }}
            </div>
        @endif
    </div>

    {{-- Detail Modal --}}
    @if ($selectedEvent)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-2xl rounded-xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <div>
                        <h2 class="text-base font-semibold">Detalle del evento</h2>
                        <p class="text-xs text-slate-500">
                            {{ $selectedEvent->occurred_at->timezone('America/Tijuana')->format('Y-m-d H:i:s') }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeEvent" class="rounded p-1 text-slate-400 hover:bg-slate-100">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="space-y-4 p-6">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Acción</p>
                            <p class="mt-1 font-mono text-slate-800">{{ $selectedEvent->action }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Usuario</p>
                            <p class="mt-1">{{ $selectedEvent->actor?->name ?? 'Sistema' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Entidad</p>
                            <p class="mt-1">
                                @if ($selectedEvent->auditable_type)
                                    {{ class_basename($selectedEvent->auditable_type) }} #{{ $selectedEvent->auditable_id }}
                                @else
                                    —
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">IP</p>
                            <p class="mt-1 font-mono text-xs">{{ $selectedEvent->ip ?? '—' }}</p>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Resumen</p>
                        <p class="mt-1 text-sm">{{ $selectedEvent->summary }}</p>
                    </div>
                    @if ($selectedEvent->user_agent)
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">User Agent</p>
                            <p class="mt-1 break-all text-xs text-slate-500">{{ $selectedEvent->user_agent }}</p>
                        </div>
                    @endif
                    @if ($selectedEvent->meta)
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Metadatos</p>
                            <pre class="mt-1 max-h-60 overflow-auto rounded-lg bg-slate-50 p-3 text-xs text-slate-700">{{ json_encode($selectedEvent->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    @endif
                </div>
                <div class="border-t border-slate-200 px-6 py-4 text-right">
                    <button type="button" wire:click="closeEvent" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</section>
