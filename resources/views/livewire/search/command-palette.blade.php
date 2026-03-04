{{-- Root div permanente: Livewire 3 requiere siempre un elemento raíz.
     El contenido del modal se muestra/oculta con @if($open). --}}
<div>
@if($open)
{{-- ─────────────────────────────────────────────────────────────────────────
     Command Palette modal
     – Overlay cierra al hacer click fuera.
     – Input con debounce 200ms.
     – Resultados agrupados por tipo con navegación ↑↓ + Enter en JS (layout).
───────────────────────────────────────────────────────────────────────────── --}}
<div
    id="command-palette-modal"
    class="fixed inset-0 z-50 flex items-start justify-center px-4 pt-[12vh]"
    role="dialog"
    aria-modal="true"
    aria-label="Búsqueda rápida"
>
    {{-- Overlay --}}
    <div
        class="absolute inset-0 bg-black/40"
        wire:click="close"
        aria-hidden="true"
    ></div>

    {{-- Card --}}
    <div class="relative w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-slate-900/10">

        {{-- ── Input ──────────────────────────────────────────────────────── --}}
        <div class="flex items-center gap-3 border-b border-slate-200 px-4">
            {{-- Search icon --}}
            <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
            </svg>

            <input
                id="cp-input"
                type="text"
                wire:model.live.debounce.200ms="q"
                wire:keydown.escape="close"
                placeholder="Busca contratos, unidades, inquilinos…"
                autocomplete="off"
                spellcheck="false"
                class="min-w-0 flex-1 bg-transparent py-3.5 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none"
            >

            {{-- Loading spinner --}}
            <div wire:loading wire:target="updatedQ" class="shrink-0">
                <svg class="h-4 w-4 animate-spin text-slate-400" viewBox="0 0 24 24" fill="none"
                     aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor"
                          d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                </svg>
            </div>

            {{-- Esc badge --}}
            <kbd class="hidden shrink-0 rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[11px] font-medium text-slate-500 sm:inline">
                Esc
            </kbd>
        </div>

        {{-- ── Results ─────────────────────────────────────────────────────── --}}
        <div id="cp-results-container" class="max-h-[60vh] overflow-y-auto">

            @php
                $trimmedQ = trim($q);
                $hasQuery = mb_strlen($trimmedQ) >= 2;
                $hasResults = count($results) > 0;

                $typeLabels = [
                    'contract' => 'Contratos',
                    'tenant'   => 'Inquilinos',
                    'unit'     => 'Unidades',
                    'property' => 'Propiedades',
                ];
                $typeOrder = ['contract', 'tenant', 'unit', 'property'];

                $typeColors = [
                    'contract' => 'bg-violet-100 text-violet-700',
                    'tenant'   => 'bg-sky-100 text-sky-700',
                    'unit'     => 'bg-emerald-100 text-emerald-700',
                    'property' => 'bg-amber-100 text-amber-700',
                ];

                $grouped = collect($results)->groupBy('type');
            @endphp

            {{-- Empty: sin query --}}
            @if(!$hasQuery)
                <div class="flex flex-col items-center gap-2 px-4 py-10 text-center">
                    <svg class="h-8 w-8 text-slate-300" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                    </svg>
                    <p class="text-sm font-medium text-slate-500">Escribe para buscar…</p>
                    <p class="text-xs text-slate-400">Ej: Casa 1, Juan García, Contrato 12</p>
                </div>

            {{-- Sin resultados --}}
            @elseif($hasQuery && !$hasResults)
                <div class="flex flex-col items-center gap-2 px-4 py-10 text-center">
                    <svg class="h-8 w-8 text-slate-300" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M15.182 16.318A4.486 4.486 0 0012.016 15a4.486 4.486 0 00-3.198 1.318
                                 M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9.75 9.75c0 .414-.168.75-.375.75S9
                                 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75zm-.375 0h.008v.015h-.008V9.75zm
                                 5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75z
                                 m-.375 0h.008v.015h-.008V9.75z"/>
                    </svg>
                    <p class="text-sm font-medium text-slate-500">Sin coincidencias</p>
                    <p class="text-xs text-slate-400">No encontramos resultados para
                        <span class="font-medium text-slate-600">"{{ $trimmedQ }}"</span>
                    </p>
                </div>

            {{-- Resultados agrupados --}}
            @else
                <div class="py-2">
                    @foreach($typeOrder as $type)
                        @if($grouped->has($type))
                            {{-- Encabezado de grupo --}}
                            <div class="px-4 pb-0.5 pt-3">
                                <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">
                                    {{ $typeLabels[$type] }}
                                </p>
                            </div>

                            @foreach($grouped[$type] as $item)
                                @php
                                    $colorClass = $typeColors[$type] ?? 'bg-slate-100 text-slate-600';
                                @endphp
                                {{-- Result row --}}
                                <div
                                    data-cp-result
                                    data-href="{{ $item['href'] }}"
                                    @if($item['href2']) data-href2="{{ $item['href2'] }}" @endif
                                    onclick="window.location.href='{{ $item['href'] }}'"
                                    class="group mx-2 flex cursor-pointer items-center gap-3 rounded-xl px-3 py-2.5 transition-colors hover:bg-slate-100"
                                    role="option"
                                    tabindex="-1"
                                >
                                    {{-- Type chip --}}
                                    <span class="inline-flex shrink-0 items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold {{ $colorClass }}">
                                        {{ mb_strtoupper(mb_substr($typeLabels[$type], 0, 3)) }}
                                    </span>

                                    {{-- Text --}}
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-slate-900">
                                            {{ $item['label'] }}
                                        </p>
                                        @if($item['sublabel'])
                                            <p class="truncate text-xs text-slate-500">
                                                {{ $item['sublabel'] }}
                                            </p>
                                        @endif
                                    </div>

                                    {{-- Status badge (contratos) --}}
                                    @if($item['badge'])
                                        <span class="hidden shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium
                                            {{ $item['badge'] === 'Activo' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}
                                            sm:inline-flex">
                                            {{ $item['badge'] }}
                                        </span>
                                    @endif

                                    {{-- Acción secundaria: registrar pago (solo contratos) --}}
                                    @if($item['href2'])
                                        <a
                                            href="{{ $item['href2'] }}"
                                            onclick="event.stopPropagation()"
                                            class="hidden shrink-0 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-medium text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 group-hover:inline-flex"
                                            title="Registrar pago"
                                        >
                                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none"
                                                 stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                      d="M12 4.5v15m7.5-7.5h-15"/>
                                            </svg>
                                            Pago
                                        </a>
                                    @endif

                                    {{-- Chevron --}}
                                    <svg class="h-3.5 w-3.5 shrink-0 text-slate-300 group-hover:text-slate-400"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                                    </svg>
                                </div>
                            @endforeach
                        @endif
                    @endforeach
                </div>
            @endif

        </div>

        {{-- ── Footer con atajos ───────────────────────────────────────────── --}}
        <div class="flex items-center gap-4 border-t border-slate-100 bg-slate-50/70 px-4 py-2.5">
            <span class="flex items-center gap-1 text-[11px] text-slate-400">
                <kbd class="rounded border border-slate-200 bg-white px-1 py-0.5 text-[10px] font-medium text-slate-500">↑↓</kbd>
                Navegar
            </span>
            <span class="flex items-center gap-1 text-[11px] text-slate-400">
                <kbd class="rounded border border-slate-200 bg-white px-1 py-0.5 text-[10px] font-medium text-slate-500">⏎</kbd>
                Abrir
            </span>
            <span class="flex items-center gap-1 text-[11px] text-slate-400">
                <kbd class="rounded border border-slate-200 bg-white px-1 py-0.5 text-[10px] font-medium text-slate-500">Esc</kbd>
                Cerrar
            </span>
            @if(count($results) > 0)
                <span class="ml-auto text-[11px] text-slate-400">
                    {{ count($results) }} {{ count($results) === 1 ? 'resultado' : 'resultados' }}
                </span>
            @endif
        </div>

    </div>
</div>
@endif
</div>
