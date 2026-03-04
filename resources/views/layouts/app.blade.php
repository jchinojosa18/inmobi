<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Inmo Admin' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">

    {{-- ─────────────────────────────────────────────────────────────────── --}}
    {{-- Overlay mobile: cierra el drawer al hacer tap fuera                 --}}
    {{-- ─────────────────────────────────────────────────────────────────── --}}
    <div
        id="sidebar-overlay"
        class="fixed inset-0 z-30 bg-black/40 opacity-0 pointer-events-none transition-opacity duration-200 lg:hidden"
        aria-hidden="true"
    ></div>

    {{-- ─────────────────────────────────────────────────────────────────── --}}
    {{-- Sidebar: fija en desktop / drawer off-canvas en mobile              --}}
    {{-- ─────────────────────────────────────────────────────────────────── --}}
    <aside
        id="app-sidebar"
        class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-white border-r border-slate-200
               -translate-x-full transition-transform duration-200 ease-in-out
               lg:translate-x-0"
        aria-label="Navegación principal"
    >
        @include('layouts.partials.sidebar')
    </aside>

    {{-- ─────────────────────────────────────────────────────────────────── --}}
    {{-- Área principal: margen izquierdo en desktop para no solapar sidebar  --}}
    {{-- ─────────────────────────────────────────────────────────────────── --}}
    <div class="flex min-h-screen flex-col lg:pl-64">

        @include('layouts.partials.topbar')

        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            <div class="mx-auto w-full max-w-7xl">
                @yield('content')
                {{ $slot ?? '' }}
            </div>

        </main>
    </div>

    {{-- Command Palette (montado globalmente) --}}
    <livewire:search.command-palette />

    @livewireScripts

    {{-- ─────────────────────────────────────────────────────────────────── --}}
    {{-- JS vanilla: drawer mobile + dropdown usuario + command palette      --}}
    {{-- ─────────────────────────────────────────────────────────────────── --}}
    <script>
    (function () {
        'use strict';

        var sidebar  = document.getElementById('app-sidebar');
        var overlay  = document.getElementById('sidebar-overlay');
        var openBtn  = document.getElementById('sidebar-open-btn');
        var closeBtn = document.getElementById('sidebar-close-btn');
        var userBtn  = document.getElementById('user-menu-btn');
        var userMenu = document.getElementById('user-menu-dropdown');

        /* ── Drawer ─────────────────────────────────────────────────────── */
        function openDrawer() {
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('translate-x-0');
            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-100', 'pointer-events-auto');
            openBtn.setAttribute('aria-expanded', 'true');
            if (closeBtn) closeBtn.focus();
        }

        function closeDrawer() {
            sidebar.classList.add('-translate-x-full');
            sidebar.classList.remove('translate-x-0');
            overlay.classList.add('opacity-0', 'pointer-events-none');
            overlay.classList.remove('opacity-100', 'pointer-events-auto');
            openBtn.setAttribute('aria-expanded', 'false');
            openBtn.focus();
        }

        if (openBtn)  openBtn.addEventListener('click', openDrawer);
        if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
        if (overlay)  overlay.addEventListener('click', closeDrawer);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && sidebar && sidebar.classList.contains('translate-x-0')) {
                closeDrawer();
            }
        });

        /* ── Dropdown usuario ────────────────────────────────────────────── */
        if (userBtn && userMenu) {
            userBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                var isOpen = !userMenu.classList.contains('hidden');
                userMenu.classList.toggle('hidden', isOpen);
                userBtn.setAttribute('aria-expanded', String(!isOpen));
            });

            userMenu.addEventListener('click', function (e) {
                e.stopPropagation();
            });

            document.addEventListener('click', function () {
                userMenu.classList.add('hidden');
                userBtn.setAttribute('aria-expanded', 'false');
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !userMenu.classList.contains('hidden')) {
                    userMenu.classList.add('hidden');
                    userBtn.setAttribute('aria-expanded', 'false');
                    userBtn.focus();
                }
            });
        }
    }());

    /* ── Command Palette ─────────────────────────────────────────────────── */
    (function () {
        'use strict';

        var cpSelectedIndex = -1;

        function cpIsOpen() {
            return !!document.getElementById('command-palette-modal');
        }

        function cpGetResults() {
            return Array.from(document.querySelectorAll('[data-cp-result]'));
        }

        function cpClearSelection(items) {
            items.forEach(function (el) {
                el.classList.remove('bg-slate-100');
                el.classList.add('hover:bg-slate-100');
            });
        }

        function cpApplySelection(items) {
            cpClearSelection(items);
            if (cpSelectedIndex >= 0 && items[cpSelectedIndex]) {
                items[cpSelectedIndex].classList.add('bg-slate-100');
                items[cpSelectedIndex].scrollIntoView({ block: 'nearest' });
            }
        }

        // Cmd+K / Ctrl+K → abrir palette
        document.addEventListener('keydown', function (e) {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                Livewire.dispatch('open-command-palette');
                return;
            }

            if (!cpIsOpen()) return;

            var items = cpGetResults();

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                cpSelectedIndex = Math.min(cpSelectedIndex + 1, items.length - 1);
                cpApplySelection(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                cpSelectedIndex = Math.max(cpSelectedIndex - 1, 0);
                cpApplySelection(items);
            } else if (e.key === 'Enter' && cpSelectedIndex >= 0 && items[cpSelectedIndex]) {
                e.preventDefault();
                var href = items[cpSelectedIndex].dataset.href;
                if (href) window.location.href = href;
            }
            // Escape: manejado por wire:keydown.escape en el input + dispatch como backup
        });

        // Reset selección cuando Livewire actualiza resultados
        document.addEventListener('livewire:updated', function () {
            if (cpIsOpen()) {
                cpSelectedIndex = -1;
                cpClearSelection(cpGetResults());
            }
        });

        // Foco automático en el input al abrir el modal
        window.addEventListener('cp-opened', function () {
            setTimeout(function () {
                var input = document.getElementById('cp-input');
                if (input) input.focus();
            }, 40);
        });
    }());
    </script>

</body>
</html>
