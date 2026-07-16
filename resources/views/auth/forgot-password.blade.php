@extends('layouts.guest', ['title' => __('auth.forgot_password.title')])

@section('content')
    <div class="mx-4 w-full max-w-md sm:mx-auto">
        <div class="rounded-2xl border border-slate-200/60 bg-white/80 p-6 shadow-xl shadow-slate-900/10 backdrop-blur dark:border-slate-700/80 dark:bg-slate-900/85 dark:shadow-black/40 sm:p-8">
            <header class="mb-6 space-y-2">
                <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-600 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300">
                    {{ __('auth.forgot_password.badge') }}
                </div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">{{ __('auth.forgot_password.heading') }}</h1>
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    {{ __('auth.forgot_password.description') }}
                </p>
            </header>

            @if (session('status'))
                <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/35 dark:text-emerald-200" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @if (! empty($throttleMessage))
                <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/35 dark:text-amber-200" role="alert">
                    {{ $throttleMessage }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200" role="alert">
                    <p class="font-medium">{{ __('auth.forgot_password.check_errors') }}</p>
                    <ul class="mt-2 list-disc space-y-1 pl-4">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="space-y-6">
                @csrf

                <div>
                    <label for="email" class="mb-2 block text-sm font-medium leading-5 text-slate-700 dark:text-slate-200">{{ __('auth.email') }}</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="{{ __('auth.email_placeholder') }}"
                        value="{{ old('email') }}"
                        class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 leading-6 text-base text-slate-900 placeholder:text-slate-400 focus:border-slate-500 focus:outline-none focus:ring-4 focus:ring-slate-900/10 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-400 dark:focus:border-slate-400 dark:focus:ring-slate-100/10"
                    >
                </div>

                <button
                    type="submit"
                    class="inline-flex h-11 w-full items-center justify-center rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-900/20 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white dark:focus-visible:ring-slate-100/20"
                >
                    {{ __('auth.forgot_password.submit') }}
                </button>
            </form>

            <p class="mt-4 text-center text-sm text-slate-600 dark:text-slate-200">
                <a
                    href="{{ route('login') }}"
                    class="font-medium underline-offset-4 hover:text-slate-900 hover:underline dark:hover:text-white"
                >
                    {{ __('auth.forgot_password.back_to_login') }}
                </a>
            </p>
        </div>
    </div>
@endsection
