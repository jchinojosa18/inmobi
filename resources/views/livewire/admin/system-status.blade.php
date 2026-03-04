<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Admin · System</h1>
            <p class="mt-1 text-sm text-slate-600">Checklist técnico de salud operativa para producción.</p>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">APP_ENV</p>
            <p class="mt-2 text-lg font-semibold text-slate-900">{{ $appStatus['app_env'] }}</p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">APP_DEBUG</p>
            <p class="mt-2 text-lg font-semibold text-slate-900">{{ $appStatus['app_debug'] }}</p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">PHP Version</p>
            <p class="mt-2 text-lg font-semibold text-slate-900">{{ $appStatus['php_version'] }}</p>
        </article>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <article class="rounded-xl border {{ $dbStatus['ok'] ? 'border-emerald-200' : 'border-red-200' }} bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-900">Database</h2>
                <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $dbStatus['ok'] ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                    {{ $dbStatus['ok'] ? 'OK' : 'ERROR' }}
                </span>
            </div>
            <p class="mt-2 text-sm text-slate-700">{{ $dbStatus['message'] }}</p>
        </article>

        <article class="rounded-xl border {{ $redisStatus['ok'] ? 'border-emerald-200' : 'border-red-200' }} bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-900">Redis</h2>
                <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $redisStatus['ok'] ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                    {{ $redisStatus['ok'] ? 'OK' : 'ERROR' }}
                </span>
            </div>
            <p class="mt-2 text-sm text-slate-700">{{ $redisStatus['message'] }}</p>
        </article>

        <article class="rounded-xl border {{ $storageStatus['ok'] ? 'border-emerald-200' : 'border-amber-200' }} bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-900">Storage</h2>
                <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $storageStatus['ok'] ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                    {{ $storageStatus['ok'] ? 'OK' : 'REVISAR' }}
                </span>
            </div>
            <ul class="mt-2 space-y-1 text-sm text-slate-700">
                <li>Writable: {{ $storageStatus['writable'] ? 'sí' : 'no' }}</li>
                <li>Public link (`storage:link`): {{ $storageStatus['public_link_ok'] ? 'sí' : 'no' }}</li>
                <li>{{ $storageStatus['message'] }}</li>
            </ul>
        </article>

        <article class="rounded-xl border {{ $schedulerStatus['ok'] ? 'border-emerald-200' : 'border-amber-200' }} bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-900">Scheduler</h2>
                <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $schedulerStatus['ok'] ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                    {{ $schedulerStatus['ok'] ? 'OK' : 'STALE' }}
                </span>
            </div>
            <p class="mt-2 text-sm text-slate-700">{{ $schedulerStatus['message'] }}</p>
            <p class="mt-1 text-xs text-slate-500">Última corrida: {{ $schedulerStatus['last_run'] ?? 'sin registro' }} · fuente: {{ $schedulerStatus['source'] }}</p>
        </article>

        <article class="rounded-xl border {{ $queueStatus['ok'] ? 'border-emerald-200' : 'border-amber-200' }} bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-900">Queue Worker</h2>
                <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $queueStatus['ok'] ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                    {{ $queueStatus['ok'] ? 'OK' : 'REVISAR' }}
                </span>
            </div>
            <p class="mt-2 text-sm text-slate-700">{{ $queueStatus['message'] }}</p>
            <p class="mt-1 text-xs text-slate-500">Última corrida: {{ $queueStatus['last_run'] ?? 'sin registro' }} · fuente: {{ $queueStatus['source'] }}</p>
        </article>

        <article class="rounded-xl border {{ $backupStatus['ok'] ? 'border-emerald-200' : 'border-amber-200' }} bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-900">Backups</h2>
                <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $backupStatus['ok'] ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                    {{ $backupStatus['ok'] ? 'OK' : 'REVISAR' }}
                </span>
            </div>
            <p class="mt-2 text-sm text-slate-700">{{ $backupStatus['message'] }}</p>
            <p class="mt-1 text-xs text-slate-500">Última corrida: {{ $backupStatus['last_run'] ?? 'sin registro' }} · fuente: {{ $backupStatus['source'] }}</p>
        </article>
    </div>
</section>
