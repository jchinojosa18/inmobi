@php
    /** @var \App\Models\OrganizationInvitation|null $invitation */
    $isInvitationFlow = isset($invitation) && $invitation !== null && isset($inviteToken) && is_string($inviteToken);
@endphp

@extends('layouts.guest', ['title' => $isInvitationFlow ? __('auth.register.title_invite') : __('auth.register.title')])

@section('content')

    <div class="mx-4 w-full max-w-md sm:mx-auto">
        <div class="rounded-2xl border border-slate-200/60 bg-white/80 p-6 shadow-xl shadow-slate-900/10 backdrop-blur dark:border-slate-700/80 dark:bg-slate-900/85 dark:shadow-black/40 sm:p-8">
            <header class="mb-6 space-y-2">
                <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-600 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300">
                    {{ $isInvitationFlow ? __('auth.register.badge_invite') : __('auth.register.badge') }}
                </div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">AXIS</h1>
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    {{ $isInvitationFlow ? __('auth.register.subtitle_invite') : __('auth.register.subtitle') }}
                </p>
            </header>

            @if ($errors->any())
                <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200" role="alert">
                    <p class="font-medium">{{ __('auth.register.check_errors') }}</p>
                    <ul class="mt-2 list-disc space-y-1 pl-4">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! empty($throttleMessage))
                <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/35 dark:text-amber-200" role="alert">
                    {{ $throttleMessage }}
                </div>
            @endif

            <form method="POST" action="{{ route('register.store') }}" class="space-y-5">
                @csrf
                @if ($isInvitationFlow)
                    <input type="hidden" name="invite_token" value="{{ old('invite_token', $inviteToken) }}">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        Te unirás a: <strong>{{ $invitation->organization?->name ?? 'Organización' }}</strong>
                    </div>
                @endif

                @unless ($isInvitationFlow)
                    <div>
                        <label for="organization_name" class="mb-2 block text-sm font-medium leading-5 text-slate-700 dark:text-slate-200">Empresa</label>
                        <input
                            id="organization_name"
                            name="organization_name"
                            type="text"
                            required
                            autofocus
                            value="{{ old('organization_name') }}"
                            placeholder="Inmobiliaria Acme"
                            class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-900 placeholder:text-slate-400 focus:border-slate-500 focus:outline-none focus:ring-4 focus:ring-slate-900/10 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:focus:border-slate-400 dark:focus:ring-slate-100/10"
                        >
                    </div>
                @endunless

                <div>
                    <label for="name" class="mb-2 block text-sm font-medium leading-5 text-slate-700 dark:text-slate-200">Nombre</label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        required
                        value="{{ old('name') }}"
                        placeholder="Tu nombre"
                        class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-900 placeholder:text-slate-400 focus:border-slate-500 focus:outline-none focus:ring-4 focus:ring-slate-900/10 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:focus:border-slate-400 dark:focus:ring-slate-100/10"
                    >
                </div>

                <div>
                    <label for="email" class="mb-2 block text-sm font-medium leading-5 text-slate-700 dark:text-slate-200">{{ __('auth.email') }}</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        required
                        value="{{ old('email', $isInvitationFlow ? $invitation->email : '') }}"
                        placeholder="{{ __('auth.email_placeholder') }}"
                        @if ($isInvitationFlow) readonly @endif
                        class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-900 placeholder:text-slate-400 focus:border-slate-500 focus:outline-none focus:ring-4 focus:ring-slate-900/10 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:focus:border-slate-400 dark:focus:ring-slate-100/10"
                    >
                </div>

                <div>
                    <label for="password" class="mb-2 block text-sm font-medium leading-5 text-slate-700 dark:text-slate-200">{{ __('auth.login.password') }}</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="new-password"
                        placeholder="{{ __('auth.reset_password.new_password_placeholder') }}"
                        class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-900 placeholder:text-slate-400 focus:border-slate-500 focus:outline-none focus:ring-4 focus:ring-slate-900/10 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:focus:border-slate-400 dark:focus:ring-slate-100/10"
                    >
                </div>

                <div>
                    <label for="password_confirmation" class="mb-2 block text-sm font-medium leading-5 text-slate-700 dark:text-slate-200">{{ __('auth.reset_password.confirm_password') }}</label>
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        placeholder="{{ __('auth.reset_password.confirm_password_placeholder') }}"
                        class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-900 placeholder:text-slate-400 focus:border-slate-500 focus:outline-none focus:ring-4 focus:ring-slate-900/10 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:focus:border-slate-400 dark:focus:ring-slate-100/10"
                    >
                </div>

                <button
                    type="submit"
                    class="inline-flex h-11 w-full items-center justify-center rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-900/20 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white dark:focus-visible:ring-slate-100/20"
                >
                    {{ __('auth.login.create_account') }}
                </button>
            </form>

            @if (Route::has('login'))
                <p class="mt-4 text-center text-sm text-slate-600 dark:text-slate-200">
                    ¿Ya tienes cuenta?
                    <a
                        href="{{ route('login') }}"
                        class="font-medium underline-offset-4 hover:text-slate-900 hover:underline dark:hover:text-white"
                    >
                        {{ __('auth.register.login_link') }}
                    </a>
                </p>
            @endif
        </div>
    </div>
@endsection
