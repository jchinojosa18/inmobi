{{-- Root div permanente: Livewire 3 requiere siempre un elemento raíz.
     El contenido del modal se muestra/oculta con @if($open). --}}
<div>
@if($open)
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
        wire:click="handleEscape"
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
                wire:keydown.escape="handleEscape"
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
                $hasEntityQuery = mb_strlen($trimmedQ) >= 2;
                $hasResults = count($results) > 0;
                $actions = $this->filteredActions;
                $hasActions = count($actions) > 0;
                $totalNavigable = count($this->navigableItems);

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

            <div class="py-2">
                @if($this->confirmingActionLabel)
                    <div class="mx-2 mb-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        Confirmar acción:
                        <span class="font-semibold">{{ $this->confirmingActionLabel }}</span>.
                        Presiona <kbd class="rounded border border-amber-300 bg-white px-1 py-0.5 text-[10px] font-medium text-amber-700">Enter</kbd>
                        para ejecutar o <kbd class="rounded border border-amber-300 bg-white px-1 py-0.5 text-[10px] font-medium text-amber-700">Esc</kbd> para cancelar.
                    </div>
                @endif

                <div class="px-4 pb-0.5 pt-2">
                    <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">
                        Acciones
                    </p>
                </div>

                @if($hasActions)
                    @foreach($actions as $action)
                        <button
                            type="button"
                            wire:key="cp-action-{{ $action['id'] }}"
                            wire:click="executeAction('{{ $action['id'] }}')"
                            data-cp-result
                            data-cp-kind="action"
                            class="group mx-2 flex w-[calc(100%-1rem)] items-center gap-3 rounded-xl px-3 py-2.5 text-left transition-colors hover:bg-slate-100"
                            role="option"
                            tabindex="-1"
                        >
                            <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-slate-100 text-slate-500">
                                @switch($action['icon'] ?? '')
                                    @case('currency-dollar')
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18m4-14H9.75a2.25 2.25 0 100 4.5h4.5a2.25 2.25 0 110 4.5H8" />
                                        </svg>
                                        @break
                                    @case('receipt-percent')
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3h10.5a.75.75 0 01.75.75v16.5l-3-1.5-3 1.5-3-1.5-3 1.5V3.75A.75.75 0 016.75 3z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 9h6M9 13.5h3.75" />
                                        </svg>
                                        @break
                                    @case('document-plus')
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 3H6.75A2.25 2.25 0 004.5 5.25v13.5A2.25 2.25 0 006.75 21h10.5a2.25 2.25 0 002.25-2.25V10.5L14.25 3z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 3v7.5h7.5M12 12v6m3-3H9" />
                                        </svg>
                                        @break
                                    @case('home')
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5L12 3l9 7.5M6 9.75v10.5A.75.75 0 006.75 21h10.5a.75.75 0 00.75-.75V9.75" />
                                        </svg>
                                        @break
                                    @case('banknotes')
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.5h19.5v9h-19.5z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12h.008v.008H6zM18 12h.008v.008H18zM12 15a3 3 0 100-6 3 3 0 000 6z" />
                                        </svg>
                                        @break
                                    @case('document-text')
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 3H6.75A2.25 2.25 0 004.5 5.25v13.5A2.25 2.25 0 006.75 21h10.5a2.25 2.25 0 002.25-2.25V10.5L14.25 3z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 12h7.5M8.25 15.75h7.5M8.25 8.25h3" />
                                        </svg>
                                        @break
                                    @case('chart-bar')
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5h15M7.5 16.5V9m4.5 7.5V6m4.5 10.5v-4.5" />
                                        </svg>
                                        @break
                                    @case('calendar-days')
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v3m10.5-3v3M4.5 8.25h15M5.25 5.25h13.5A1.5 1.5 0 0120.25 6.75v12A1.5 1.5 0 0118.75 20.25H5.25a1.5 1.5 0 01-1.5-1.5v-12a1.5 1.5 0 011.5-1.5z" />
                                        </svg>
                                        @break
                                    @default
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6" />
                                        </svg>
                                @endswitch
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-slate-900">{{ $action['label'] }}</p>
                            </div>

                            @if(($action['requires_confirmation'] ?? false) && $this->confirmingActionId === $action['id'])
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700">
                                    Confirmar
                                </span>
                            @endif

                            <span class="rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] font-medium text-slate-500">
                                Enter
                            </span>
                        </button>
                    @endforeach
                @else
                    <div class="mx-2 rounded-lg border border-dashed border-slate-200 px-3 py-3 text-xs text-slate-500">
                        No hay acciones que coincidan con <span class="font-medium text-slate-600">"{{ $trimmedQ }}"</span>.
                    </div>
                @endif

                <div class="mx-2 my-2 border-t border-slate-100"></div>

                @if(!$hasEntityQuery)
                    <div class="px-4 pb-3 pt-1 text-xs text-slate-500">
                        Escribe al menos 2 caracteres para buscar contratos, unidades, inquilinos o propiedades.
                    </div>
                @elseif(!$hasResults)
                    <div class="px-4 pb-3 pt-1 text-xs text-slate-500">
                        Sin resultados de entidades para
                        <span class="font-medium text-slate-700">"{{ $trimmedQ }}"</span>.
                    </div>
                @else
                    @foreach($typeOrder as $type)
                        @if($grouped->has($type))
                            <div class="px-4 pb-0.5 pt-3">
                                <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">
                                    {{ $typeLabels[$type] }}
                                </p>
                            </div>

                            @foreach($grouped[$type] as $item)
                                @php
                                    $colorClass = $typeColors[$type] ?? 'bg-slate-100 text-slate-600';
                                @endphp
                                <div
                                    data-cp-result
                                    data-cp-kind="result"
                                    data-href="{{ $item['href'] }}"
                                    @if($item['href2']) data-href2="{{ $item['href2'] }}" @endif
                                    onclick="window.location.href='{{ $item['href'] }}'"
                                    class="group mx-2 flex cursor-pointer items-center gap-3 rounded-xl px-3 py-2.5 transition-colors hover:bg-slate-100"
                                    role="option"
                                    tabindex="-1"
                                >
                                    <span class="inline-flex shrink-0 items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold {{ $colorClass }}">
                                        {{ mb_strtoupper(mb_substr($typeLabels[$type], 0, 3)) }}
                                    </span>

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

                                    @if($item['badge'])
                                        <span class="hidden shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium
                                            {{ $item['badge'] === 'Activo' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}
                                            sm:inline-flex">
                                            {{ $item['badge'] }}
                                        </span>
                                    @endif

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
                Ejecutar
            </span>
            <span class="flex items-center gap-1 text-[11px] text-slate-400">
                <kbd class="rounded border border-slate-200 bg-white px-1 py-0.5 text-[10px] font-medium text-slate-500">Esc</kbd>
                Cerrar
            </span>
            @if($totalNavigable > 0)
                <span class="ml-auto text-[11px] text-slate-400">
                    {{ $totalNavigable }} {{ $totalNavigable === 1 ? 'elemento' : 'elementos' }}
                </span>
            @endif
        </div>

    </div>
</div>
@endif
</div>
