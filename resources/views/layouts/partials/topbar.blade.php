<header class="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-3 border-b border-slate-200 bg-white px-4 sm:px-6">
    @php
        $organizationId = (int) (auth()->user()?->organization_id ?? 0);
        $plazas = collect();
        $showPlazaSelector = false;
        $currentPlazaId = \App\Support\TenantContext::currentPlazaId();
        $currentPlazaLabel = 'Todas';

        if ($organizationId > 0) {
            $plazas = \App\Models\Plaza::query()
                ->orderByDesc('is_default')
                ->orderBy('nombre')
                ->get(['id', 'nombre']);

            $showPlazaSelector = $plazas->count() > 1;

            if ($currentPlazaId !== null) {
                $selectedPlaza = $plazas->firstWhere('id', $currentPlazaId);
                if ($selectedPlaza !== null) {
                    $currentPlazaLabel = $selectedPlaza->nombre;
                }
            }
        }
    @endphp

    {{-- ─── Hamburger (solo mobile) ──────────────────────────────────────── --}}
    <button
        id="sidebar-open-btn"
        type="button"
        class="inline-flex items-center justify-center rounded-lg p-1.5 text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 lg:hidden"
        aria-label="Abrir menú de navegación"
        aria-expanded="false"
        aria-controls="app-sidebar"
    >
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
        </svg>
        <span class="sr-only">Abrir menú</span>
    </button>

    {{-- ─── Trigger Command Palette ────────────────────────────────────────── --}}
    <div class="flex flex-1 items-center">
        <button
            type="button"
            onclick="Livewire.dispatch('open-command-palette')"
            class="flex h-9 w-full max-w-xs items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 text-left transition hover:bg-slate-100 hover:border-slate-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
            aria-label="Abrir búsqueda rápida (⌘K)"
        >
            <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
            </svg>
            <span class="flex-1 text-sm text-slate-400">Buscar…</span>
            <kbd class="hidden shrink-0 rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[11px] font-medium text-slate-400 sm:inline-block">
                ⌘K
            </kbd>
        </button>
    </div>

    {{-- ─── Acciones derecha ───────────────────────────────────────────────── --}}
    <div class="flex items-center gap-2">
        @if ($showPlazaSelector)
            <form method="POST" action="{{ route('tenant.current-plaza.update') }}" class="flex items-center gap-2">
                @csrf
                <label for="topbar-plaza-select" class="text-xs font-medium text-slate-600">
                    Plaza: {{ $currentPlazaLabel }}
                </label>
                <select
                    id="topbar-plaza-select"
                    name="plaza_id"
                    onchange="this.form.submit()"
                    class="h-9 rounded-lg border border-slate-300 bg-white px-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-300"
                >
                    <option value="" @selected($currentPlazaId === null)>Todas</option>
                    @foreach ($plazas as $plaza)
                        <option value="{{ $plaza->id }}" @selected((int) $currentPlazaId === (int) $plaza->id)>{{ $plaza->nombre }}</option>
                    @endforeach
                </select>
            </form>
        @endif

        {{-- Nuevo contrato (solo desktop) --}}
        <a
            href="{{ route('contracts.create') }}"
            class="hidden items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2 lg:inline-flex"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            Nuevo contrato
        </a>

        {{-- ─── Menú de usuario ─────────────────────────────────────────── --}}
        <div class="relative" id="user-menu-container">
            <button
                id="user-menu-btn"
                type="button"
                class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-slate-700 transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
                aria-expanded="false"
                aria-haspopup="true"
                aria-controls="user-menu-dropdown"
            >
                {{-- Avatar inicial --}}
                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white" aria-hidden="true">
                    {{ mb_strtoupper(mb_substr(auth()->user()->name ?? 'U', 0, 1)) }}
                </span>
                <span class="hidden max-w-[120px] truncate text-sm font-medium text-slate-800 sm:block">
                    {{ auth()->user()->name }}
                </span>
                <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                </svg>
            </button>

            {{-- Dropdown --}}
            <div
                id="user-menu-dropdown"
                class="absolute right-0 mt-1 hidden w-56 rounded-xl border border-slate-200 bg-white py-1 shadow-lg shadow-slate-900/10"
                role="menu"
                aria-labelledby="user-menu-btn"
            >
                {{-- Info del usuario --}}
                <div class="border-b border-slate-100 px-4 py-3">
                    <p class="truncate text-sm font-medium text-slate-900">
                        {{ auth()->user()->name }}
                    </p>
                    @if (auth()->user()->organization?->name)
                        <p class="truncate text-xs text-slate-500">
                            {{ auth()->user()->organization->name }}
                        </p>
                    @endif
                    <p class="truncate text-xs text-slate-400">
                        {{ auth()->user()->email }}
                    </p>
                </div>

                {{-- Cerrar sesión --}}
                <div class="px-1 py-1">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-100 hover:text-slate-900"
                            role="menuitem"
                        >
                            <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0
                                         00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0
                                         002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/>
                            </svg>
                            Salir
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</header>
