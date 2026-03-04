<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Inmo Admin' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <div class="relative min-h-screen overflow-hidden bg-gradient-to-br from-slate-50 via-white to-slate-100 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950">
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 opacity-30 [background-image:radial-gradient(circle_at_1px_1px,rgb(148_163_184)_1px,transparent_0)] [background-size:22px_22px] dark:opacity-20"
        ></div>

        <main class="relative mx-auto flex min-h-screen w-full max-w-7xl items-center justify-center px-4 py-8 sm:px-6 lg:px-8">
            <div class="grid w-full max-w-5xl items-center gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,28rem)]">
                <section class="hidden max-w-xl rounded-3xl border border-white/45 bg-white/70 p-8 shadow-xl shadow-slate-900/10 backdrop-blur lg:block dark:border-slate-700/70 dark:bg-slate-900/55 dark:shadow-black/40">
                    <div class="space-y-6">
                        <p class="inline-flex rounded-full border border-slate-200/80 bg-white/80 px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-600 dark:border-slate-600/70 dark:bg-slate-900/60 dark:text-slate-300">
                            Plataforma inmobiliaria
                        </p>
                        <h1 class="text-3xl font-semibold leading-tight tracking-tight text-slate-900 dark:text-slate-100">
                            Opera tu cartera con disciplina financiera y trazabilidad real.
                        </h1>
                        <p class="text-sm leading-6 text-slate-600 dark:text-slate-300">
                            Inmo Admin concentra la operación diaria en un panel claro para cobrar, cerrar periodos y monitorear métricas sin perder control.
                        </p>
                        <ul class="space-y-2.5 text-sm text-slate-700 dark:text-slate-200">
                            <li class="flex items-start gap-2.5">
                                <span class="mt-1.5 h-2 w-2 rounded-full bg-emerald-500"></span>
                                Cobranza priorizada por vencimiento y periodo de gracia.
                            </li>
                            <li class="flex items-start gap-2.5">
                                <span class="mt-1.5 h-2 w-2 rounded-full bg-emerald-500"></span>
                                Multas automáticas con cálculo diario compuesto.
                            </li>
                            <li class="flex items-start gap-2.5">
                                <span class="mt-1.5 h-2 w-2 rounded-full bg-emerald-500"></span>
                                Reportes y cierres mensuales listos para auditoría.
                            </li>
                        </ul>
                    </div>
                </section>

                <section class="flex items-center justify-center lg:justify-end">
                    <div class="w-full max-w-md">
                        {{ $slot ?? '' }}
                        @yield('content')
                    </div>
                </section>
            </div>
        </main>
    </div>
</body>
</html>
