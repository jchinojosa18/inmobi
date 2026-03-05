@extends('layouts.guest', ['title' => 'Verifica tu correo | Inmo Admin'])

@section('content')
    <div class="mx-4 w-full max-w-md sm:mx-auto">
        <div class="rounded-2xl border border-slate-200/60 bg-white/80 p-6 shadow-xl shadow-slate-900/10 backdrop-blur dark:border-slate-700/80 dark:bg-slate-900/85 dark:shadow-black/40 sm:p-8">
            <header class="mb-6 space-y-2">
                <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-600 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300">
                    VERIFICACIÓN
                </div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">Verifica tu correo</h1>
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Te enviamos un enlace de verificación a tu email. Debes confirmarlo para acceder al panel.
                </p>
            </header>

            @if (session('status') === 'verification-link-sent')
                <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/35 dark:text-emerald-200">
                    Te enviamos un enlace nuevo de verificación.
                </div>
            @endif

            @if (! empty($throttleMessage))
                <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/35 dark:text-amber-200" role="alert">
                    {{ $throttleMessage }}
                </div>
            @endif

            <div class="space-y-3">
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex h-11 w-full items-center justify-center rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-900/20 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white dark:focus-visible:ring-slate-100/20"
                    >
                        Reenviar enlace
                    </button>
                </form>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex h-11 w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700/60"
                    >
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
