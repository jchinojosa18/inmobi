@extends('layouts.guest', ['title' => 'Nueva contraseña | Inmo Admin'])

@section('content')
    <div class="mx-4 w-full max-w-md sm:mx-auto">
        <div class="rounded-2xl border border-slate-200/60 bg-white/80 p-6 shadow-xl shadow-slate-900/10 backdrop-blur dark:border-slate-700/80 dark:bg-slate-900/85 dark:shadow-black/40 sm:p-8">
            <header class="mb-6 space-y-2">
                <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-600 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300">
                    NUEVA CONTRASEÑA
                </div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">Restablecer contraseña</h1>
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Elige una contraseña nueva para tu cuenta.
                </p>
            </header>

            @if ($errors->any())
                <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200" role="alert">
                    <p class="font-medium">Revisa los datos e intenta nuevamente.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-4">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div>
                    <label for="email" class="mb-2 block text-sm font-medium leading-5 text-slate-700 dark:text-slate-200">Email</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        required
                        autofocus
                        autocomplete="username"
                        value="{{ old('email', $email) }}"
                        class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-900 placeholder:text-slate-400 focus:border-slate-500 focus:outline-none focus:ring-4 focus:ring-slate-900/10 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:focus:border-slate-400 dark:focus:ring-slate-100/10"
                    >
                </div>

                <div>
                    <label for="password" class="mb-2 block text-sm font-medium leading-5 text-slate-700 dark:text-slate-200">Nueva contraseña</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="new-password"
                        placeholder="Mínimo 8 caracteres"
                        class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-900 placeholder:text-slate-400 focus:border-slate-500 focus:outline-none focus:ring-4 focus:ring-slate-900/10 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:focus:border-slate-400 dark:focus:ring-slate-100/10"
                    >
                </div>

                <div>
                    <label for="password_confirmation" class="mb-2 block text-sm font-medium leading-5 text-slate-700 dark:text-slate-200">Confirmar contraseña</label>
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        placeholder="Repite tu contraseña"
                        class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-900 placeholder:text-slate-400 focus:border-slate-500 focus:outline-none focus:ring-4 focus:ring-slate-900/10 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:focus:border-slate-400 dark:focus:ring-slate-100/10"
                    >
                </div>

                <button
                    type="submit"
                    class="inline-flex h-11 w-full items-center justify-center rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-900/20 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white dark:focus-visible:ring-slate-100/20"
                >
                    Guardar contraseña
                </button>
            </form>
        </div>
    </div>
@endsection
