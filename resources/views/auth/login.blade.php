@extends('layouts.guest', ['title' => 'Login | Inmo Admin'])

@section('content')
    <div class="mx-4 w-full max-w-md sm:mx-auto">
        <div class="rounded-2xl border border-slate-200/60 bg-white/80 p-6 shadow-xl shadow-slate-900/10 backdrop-blur dark:border-slate-700/80 dark:bg-slate-900/85 dark:shadow-black/40 sm:p-8">
            <header class="mb-6 space-y-2">
                <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-600 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300">
                    LOGIN
                </div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">Inmo Admin</h1>
            </header>

            @if (session('status'))
                <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/35 dark:text-emerald-200" role="status">
                    {{ session('status') }}
                </div>
            @endif

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

            <form id="login-form" method="POST" action="{{ route('login.store') }}" class="space-y-6">
                @csrf

                {{-- Email --}}
                <div>
                    <label for="email" class="mb-2 block text-sm font-medium leading-5 text-slate-700 dark:text-slate-200">Email</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="tu@email.com"
                        value="{{ old('email') }}"
                        aria-invalid="@error('email') true @else false @enderror"
                        aria-describedby="@error('email') email-error @enderror"
                        class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 leading-6 text-base text-slate-900 placeholder:text-slate-400 focus:border-slate-500 focus:outline-none focus:ring-4 focus:ring-slate-900/10 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-400 dark:focus:border-slate-400 dark:focus:ring-slate-100/10"
                    >
                    @error('email')
                        <p id="email-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Contraseña --}}
                <div>
                    <label for="password" class="mb-2 block text-sm font-medium leading-5 text-slate-700 dark:text-slate-200">Contraseña</label>
                    <div class="relative h-11 w-full">
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            placeholder="Ingresa tu contraseña"
                            aria-invalid="@error('password') true @else false @enderror"
                            aria-describedby="@error('password') password-error @enderror"
                            class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 pr-12 leading-6 text-base text-slate-900 placeholder:text-slate-400 focus:border-slate-500 focus:outline-none focus:ring-4 focus:ring-slate-900/10 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-400 dark:focus:border-slate-400 dark:focus:ring-slate-100/10"
                        >
                        <button
                            type="button"
                            id="toggle-password"
                            class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center justify-center rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-slate-400 dark:text-slate-400 dark:hover:bg-slate-700/40 dark:hover:text-slate-200"
                            aria-label="Mostrar contraseña"
                            aria-pressed="false"
                        >
                            <svg id="icon-eye" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.46 12C3.73 7.94 7.52 5 12 5s8.27 2.94 9.54 7c-1.27 4.06-5.06 7-9.54 7s-8.27-2.94-9.54-7z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                            <svg id="icon-eye-off" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.58 10.58A2 2 0 0012 14a2 2 0 001.42-.58M9.88 5.09A9.65 9.65 0 0112 5c4.48 0 8.27 2.94 9.54 7a10.94 10.94 0 01-3.06 4.56M6.23 6.23A10.94 10.94 0 002.46 12c1.27 4.06 5.06 7 9.54 7 1.57 0 3.05-.35 4.37-.98" />
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <p id="password-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Recordarme / Olvidaste contraseña --}}
                <div class="mt-4 flex items-center justify-between gap-4">
                    <label for="remember" class="inline-flex cursor-pointer items-center gap-2 text-sm leading-5 text-slate-600 dark:text-slate-300">
                        <input
                            id="remember"
                            name="remember"
                            type="checkbox"
                            value="1"
                            @checked(old('remember'))
                            class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900/20 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                        >
                        <span>Recordarme</span>
                    </label>

                    @if (Route::has('password.request'))
                        <a
                            href="{{ route('password.request') }}"
                            class="whitespace-nowrap text-sm leading-5 text-slate-600 underline-offset-4 transition hover:text-slate-900 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2 dark:text-slate-300 dark:hover:text-slate-100"
                        >
                            ¿Olvidaste tu contraseña?
                        </a>
                    @else
                        <a
                            href="mailto:soporte@inmo-admin.local?subject=Recuperar%20acceso"
                            class="whitespace-nowrap text-sm leading-5 text-slate-600 underline-offset-4 transition hover:text-slate-900 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2 dark:text-slate-300 dark:hover:text-slate-100"
                        >
                            ¿Olvidaste tu contraseña?
                        </a>
                    @endif
                </div>

                <button
                    id="login-submit"
                    type="submit"
                    class="mt-2 inline-flex h-11 w-full items-center justify-center rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-900/20 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white dark:focus-visible:ring-slate-100/20"
                >
                    <svg id="login-submit-spinner" class="mr-2 hidden h-4 w-4 animate-spin text-current" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                        <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span id="login-submit-label">Entrar</span>
                </button>
            </form>

            @if (Route::has('register'))
                <p class="mt-4 text-center text-sm text-slate-600 dark:text-slate-200">
                    ¿No tienes cuenta?
                    <a
                        href="{{ route('register') }}"
                        class="font-medium underline-offset-4 hover:text-slate-900 hover:underline dark:hover:text-white"
                    >
                        Crear cuenta
                    </a>
                </p>
            @endif
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const passwordInput = document.getElementById('password');
            const toggleButton = document.getElementById('toggle-password');
            const iconEye = document.getElementById('icon-eye');
            const iconEyeOff = document.getElementById('icon-eye-off');

            if (!passwordInput || !toggleButton || !iconEye || !iconEyeOff) {
                return;
            }

            toggleButton.addEventListener('click', function () {
                const showing = passwordInput.type === 'text';
                passwordInput.type = showing ? 'password' : 'text';
                toggleButton.setAttribute('aria-pressed', showing ? 'false' : 'true');
                toggleButton.setAttribute('aria-label', showing ? 'Mostrar contraseña' : 'Ocultar contraseña');
                iconEye.classList.toggle('hidden', !showing);
                iconEyeOff.classList.toggle('hidden', showing);
            });

            const loginForm = document.getElementById('login-form');
            const submitButton = document.getElementById('login-submit');
            const submitSpinner = document.getElementById('login-submit-spinner');
            const submitLabel = document.getElementById('login-submit-label');

            if (!loginForm || !submitButton || !submitSpinner || !submitLabel) {
                return;
            }

            loginForm.addEventListener('submit', function () {
                submitButton.setAttribute('disabled', 'disabled');
                submitSpinner.classList.remove('hidden');
                submitLabel.textContent = 'Entrando...';
            });
        });
    </script>
@endsection
