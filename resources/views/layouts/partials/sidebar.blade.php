@php
    $active   = 'bg-slate-100 text-slate-900 font-medium';
    $inactive = 'text-slate-600 hover:bg-slate-50 hover:text-slate-900';
    $aIcon    = 'text-slate-700';
    $iIcon    = 'text-slate-400 group-hover:text-slate-600';
    $linkBase = 'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors ';
    $iconBase = 'h-4 w-4 shrink-0 ';

    $lc = fn(string ...$p) => $linkBase . (request()->routeIs(...$p) ? $active   : $inactive);
    $ic = fn(string ...$p) => $iconBase  . (request()->routeIs(...$p) ? $aIcon    : $iIcon);
@endphp

{{-- ─── Branding ──────────────────────────────────────────────────────── --}}
<div class="flex h-14 shrink-0 items-center gap-2 border-b border-slate-200 px-4">
    <a href="{{ route('dashboard') }}"
       class="flex-1 text-base font-semibold tracking-tight text-slate-900">
        Inmo Admin
    </a>

    {{-- Close button – visible solo en mobile --}}
    <button id="sidebar-close-btn" type="button"
            class="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 lg:hidden"
            aria-label="Cerrar menú de navegación">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </button>
</div>

{{-- ─── Navegación ─────────────────────────────────────────────────────── --}}
<nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6" aria-label="Menú principal">

    {{-- OPERACIÓN --}}
    <div>
        <p class="mb-1 px-3 text-[11px] font-semibold uppercase tracking-widest text-slate-400">
            Operación
        </p>
        <ul class="space-y-0.5">
            <li>
                <a href="{{ route('dashboard') }}" class="{{ $lc('dashboard') }}">
                    <svg class="{{ $ic('dashboard') }}" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12
                                 M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75
                                 v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21
                                 h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
                    </svg>
                    Dashboard
                </a>
            </li>
            <li>
                <a href="{{ route('cobranza.index') }}" class="{{ $lc('cobranza.index') }}">
                    <svg class="{{ $ic('cobranza.index') }}" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3
                                 m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0
                                 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25
                                 0 004.5 19.5z"/>
                    </svg>
                    Cobranza
                </a>
            </li>
            <li>
                <a href="{{ route('contracts.index') }}" class="{{ $lc('contracts.*') }}">
                    <svg class="{{ $ic('contracts.*') }}" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5
                                 A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0
                                 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125
                                 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0
                                 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                    </svg>
                    Contratos
                </a>
            </li>
        </ul>
    </div>

    {{-- CATÁLOGOS --}}
    <div>
        <p class="mb-1 px-3 text-[11px] font-semibold uppercase tracking-widest text-slate-400">
            Catálogos
        </p>
        <ul class="space-y-0.5">
            <li>
                <a href="{{ route('properties.index') }}"
                   class="{{ $lc('properties.*', 'houses.*') }}">
                    <svg class="{{ $ic('properties.*', 'houses.*') }}" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18
                                 M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15
                                 m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75
                                 c.621 0 1.125.504 1.125 1.125V21"/>
                    </svg>
                    Propiedades
                </a>
            </li>
            <li>
                <a href="{{ route('tenants.index') }}" class="{{ $lc('tenants.index') }}">
                    <svg class="{{ $ic('tenants.index') }}" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0
                                 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128
                                 v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106
                                 A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766
                                 l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375
                                 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25
                                 a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                    </svg>
                    Inquilinos
                </a>
            </li>
        </ul>
    </div>

    {{-- FINANZAS --}}
    <div>
        <p class="mb-1 px-3 text-[11px] font-semibold uppercase tracking-widest text-slate-400">
            Finanzas
        </p>
        <ul class="space-y-0.5">
            <li>
                <a href="{{ route('expenses.index') }}" class="{{ $lc('expenses.index') }}">
                    <svg class="{{ $ic('expenses.index') }}" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9 12.75l3 3m0 0l3-3m-3 3v-7.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Egresos
                </a>
            </li>
            <li>
                <a href="{{ route('reports.flow') }}" class="{{ $lc('reports.*') }}">
                    <svg class="{{ $ic('reports.*') }}" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0
                                 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375
                                 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625
                                 c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504
                                 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25
                                 a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125
                                 c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504
                                 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25
                                 a1.125 1.125 0 01-1.125-1.125V4.125z"/>
                    </svg>
                    Reporte flujo
                </a>
            </li>
            <li>
                <a href="{{ route('month-closes.index') }}" class="{{ $lc('month-closes.index') }}">
                    <svg class="{{ $ic('month-closes.index') }}" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0
                                 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0
                                 A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75
                                 m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0
                                 0121 9v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008
                                 H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008
                                 H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008
                                 v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5
                                 h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0
                                 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H18
                                 v-.008zm0 2.25h.008v.008H18V15z"/>
                    </svg>
                    Cierres
                </a>
            </li>
        </ul>
    </div>

    {{-- SISTEMA --}}
    <div>
        <p class="mb-1 px-3 text-[11px] font-semibold uppercase tracking-widest text-slate-400">
            Sistema
        </p>
        <ul class="space-y-0.5">
            <li>
                <a href="{{ route('settings.index') }}" class="{{ $lc('settings.index') }}">
                    <svg class="{{ $ic('settings.index') }}" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0
                                 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87
                                 .074.04.147.083.22.127.324.196.72.257 1.075.124l1.217
                                 -.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125
                                 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992
                                 a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005
                                 .828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125
                                 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076
                                 .124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644
                                 .869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594
                                 c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374
                                 -.312-.686-.644-.87a6.52 6.52 0 01-.22-.127
                                 c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125
                                 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431
                                 l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0
                                 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125
                                 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0
                                 011.37-.491l1.216.456c.356.133.751.072 1.076-.124
                                 .072-.044.146-.087.22-.128.332-.183.582-.495.644
                                 -.869l.214-1.281z"/>
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Configuración
                </a>
            </li>
            @if (auth()->user()?->hasRole('Admin'))
                <li>
                    <a href="{{ route('settings.invitations.index') }}" class="{{ $lc('settings.invitations.*') }}">
                        <svg class="{{ $ic('settings.invitations.*') }}" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M18 7.5v-1.5A2.25 2.25 0 0015.75 3.75h-7.5A2.25 2.25 0 006 6v1.5m12 0
                                     h1.5A1.5 1.5 0 0121 9v10.5a1.5 1.5 0 01-1.5 1.5H4.5A1.5 1.5 0 013
                                     19.5V9a1.5 1.5 0 011.5-1.5H6m12 0h-3m-6 0H6m3 0h6m-3 0v6m0 0l2.25-2.25
                                     M12 13.5l-2.25-2.25"/>
                        </svg>
                        Invitaciones
                    </a>
                </li>
                <li>
                    <a href="{{ route('settings.plazas.index') }}" class="{{ $lc('settings.plazas.*') }}">
                        <svg class="{{ $ic('settings.plazas.*') }}" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M3.75 7.5h16.5M3.75 12h16.5m-16.5 4.5h16.5M7.5 7.5
                                     a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm12 4.5a1.5 1.5 0
                                     11-3 0 1.5 1.5 0 013 0zm-7.5 4.5a1.5 1.5 0 11-3 0 1.5
                                     1.5 0 013 0z"/>
                        </svg>
                        Plazas
                    </a>
                </li>
                <li>
                    <a href="{{ route('settings.audit.index') }}" class="{{ $lc('settings.audit.*') }}">
                        <svg class="{{ $ic('settings.audit.*') }}" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0
                                     002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424
                                     48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664
                                     0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0
                                     00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0
                                     1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095
                                     4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621
                                     0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125
                                     1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375
                                     c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008
                                     H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/>
                        </svg>
                        Auditoría
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.system') }}" class="{{ $lc('admin.system') }}">
                        <svg class="{{ $ic('admin.system') }}" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0
                                     013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29
                                     9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571
                                     -.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                        </svg>
                        Admin System
                    </a>
                </li>
            @endif
        </ul>
    </div>

</nav>

{{-- ─── CTA Nuevo contrato ──────────────────────────────────────────────── --}}
<div class="shrink-0 border-t border-slate-200 p-3">
    <a href="{{ route('contracts.create') }}"
       class="flex items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
        </svg>
        Nuevo contrato
    </a>
</div>
