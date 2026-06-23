<section class="space-y-6">
    <x-ui.page-header
        title="Admin · System"
        description="Checklist técnico de salud operativa para producción."
    />

    <div class="grid gap-4 md:grid-cols-3">
        <x-ui.stat-card label="APP_ENV" :value="$appStatus['app_env']" />
        <x-ui.stat-card label="APP_DEBUG" :value="$appStatus['app_debug']" />
        <x-ui.stat-card label="PHP Version" :value="$appStatus['php_version']" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card class="{{ $dbStatus['ok'] ? 'border-emerald-200/80' : 'border-red-200/80' }}">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-900">Database</h2>
                <x-ui.badge :variant="$dbStatus['ok'] ? 'success' : 'danger'">
                    {{ $dbStatus['ok'] ? 'OK' : 'ERROR' }}
                </x-ui.badge>
            </div>
            <p class="mt-2 text-sm text-slate-700">{{ $dbStatus['message'] }}</p>
        </x-ui.card>

        <x-ui.card class="{{ $redisStatus['ok'] ? 'border-emerald-200/80' : 'border-red-200/80' }}">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-900">Redis</h2>
                <x-ui.badge :variant="$redisStatus['ok'] ? 'success' : 'danger'">
                    {{ $redisStatus['ok'] ? 'OK' : 'ERROR' }}
                </x-ui.badge>
            </div>
            <p class="mt-2 text-sm text-slate-700">{{ $redisStatus['message'] }}</p>
        </x-ui.card>

        <x-ui.card class="{{ $storageStatus['ok'] ? 'border-emerald-200/80' : 'border-amber-200/80' }}">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-900">Storage</h2>
                <x-ui.badge :variant="$storageStatus['ok'] ? 'success' : 'warning'">
                    {{ $storageStatus['ok'] ? 'OK' : 'REVISAR' }}
                </x-ui.badge>
            </div>
            <ul class="mt-2 space-y-1 text-sm text-slate-700">
                <li>Writable: {{ $storageStatus['writable'] ? 'sí' : 'no' }}</li>
                <li>Public link (`storage:link`): {{ $storageStatus['public_link_ok'] ? 'sí' : 'no' }}</li>
                <li>{{ $storageStatus['message'] }}</li>
            </ul>
        </x-ui.card>

        <x-ui.card class="{{ $schedulerStatus['ok'] ? 'border-emerald-200/80' : 'border-amber-200/80' }}">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-900">Scheduler</h2>
                <x-ui.badge :variant="$schedulerStatus['ok'] ? 'success' : 'warning'">
                    {{ $schedulerStatus['ok'] ? 'OK' : 'STALE' }}
                </x-ui.badge>
            </div>
            <p class="mt-2 text-sm text-slate-700">{{ $schedulerStatus['message'] }}</p>
            <p class="mt-1 text-xs text-slate-500">Última corrida: {{ $schedulerStatus['last_run'] ?? 'sin registro' }} · fuente: {{ $schedulerStatus['source'] }}</p>
        </x-ui.card>

        <x-ui.card class="{{ $queueStatus['ok'] ? 'border-emerald-200/80' : 'border-amber-200/80' }}">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-900">Queue Worker</h2>
                <x-ui.badge :variant="$queueStatus['ok'] ? 'success' : 'warning'">
                    {{ $queueStatus['ok'] ? 'OK' : 'REVISAR' }}
                </x-ui.badge>
            </div>
            <p class="mt-2 text-sm text-slate-700">{{ $queueStatus['message'] }}</p>
            <p class="mt-1 text-xs text-slate-500">Última corrida: {{ $queueStatus['last_run'] ?? 'sin registro' }} · fuente: {{ $queueStatus['source'] }}</p>
        </x-ui.card>

        <x-ui.card class="{{ $backupStatus['ok'] ? 'border-emerald-200/80' : 'border-amber-200/80' }}">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-900">Backups</h2>
                <x-ui.badge :variant="$backupStatus['ok'] ? 'success' : 'warning'">
                    {{ $backupStatus['ok'] ? 'OK' : 'REVISAR' }}
                </x-ui.badge>
            </div>
            <p class="mt-2 text-sm text-slate-700">{{ $backupStatus['message'] }}</p>
            <p class="mt-1 text-xs text-slate-500">Última corrida: {{ $backupStatus['last_run'] ?? 'sin registro' }} · fuente: {{ $backupStatus['source'] }}</p>
        </x-ui.card>
    </div>
</section>
