<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Inmo Admin' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
            <div class="flex items-center gap-4">
                <a href="{{ route('dashboard') }}" class="text-base font-semibold tracking-tight text-slate-900">
                    Inmo Admin
                </a>
                @auth
                    <nav class="flex flex-wrap items-center gap-3 text-sm text-slate-600">
                        <a href="{{ route('properties.index') }}" class="hover:text-slate-900">Propiedades</a>
                        <a href="{{ route('tenants.index') }}" class="hover:text-slate-900">Inquilinos</a>
                        <a href="{{ route('contracts.index') }}" class="hover:text-slate-900">Contratos</a>
                        <a href="{{ route('cobranza.index') }}" class="hover:text-slate-900">Cobranza</a>
                        <a href="{{ route('expenses.index') }}" class="hover:text-slate-900">Egresos</a>
                        <a href="{{ route('reports.flow') }}" class="hover:text-slate-900">Reporte flujo</a>
                        <a href="{{ route('month-closes.index') }}" class="hover:text-slate-900">Cierres</a>
                        <a href="{{ route('settings.index') }}" class="hover:text-slate-900">Configuración</a>
                        @if (auth()->user()?->hasRole('Admin'))
                            <a href="{{ route('admin.system') }}" class="hover:text-slate-900">Admin System</a>
                        @endif
                        <a href="{{ route('contracts.create') }}" class="hover:text-slate-900">Nuevo contrato</a>
                    </nav>
                @endauth
            </div>

            @auth
                <div class="flex items-center gap-3 text-sm">
                    <div class="text-right">
                        <p class="font-medium text-slate-800">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-slate-500">{{ auth()->user()->organization?->name ?? 'Sin organización' }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Salir
                        </button>
                    </form>
                </div>
            @endauth
        </div>
    </header>

    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        @if (session('success'))
            <div class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
        {{ $slot ?? '' }}
    </main>

    @livewireScripts
</body>
</html>
