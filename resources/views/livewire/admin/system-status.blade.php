<section class="space-y-6">
    <x-ui.page-header
        :title="__('admin.system_title')"
        :description="__('admin.system_description')"
    />

    <div class="grid gap-4 md:grid-cols-3">
        <x-ui.stat-card label="APP_ENV" :value="$appStatus['app_env']" />
        <x-ui.stat-card label="APP_DEBUG" :value="$appStatus['app_debug']" />
        <x-ui.stat-card :label="__('admin.php_version')" :value="$appStatus['php_version']" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card class="{{ $dbStatus['ok'] ? 'border-emerald-200/80' : 'border-red-200/80' }}">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-900">{{ __('admin.database') }}</h2>
                <x-ui.badge :variant="$dbStatus['ok'] ? 'success' : 'danger'">
                    {{ $dbStatus['ok'] ? __('admin.status_ok') : __('admin.status_error') }}
                </x-ui.badge>
            </div>
            <p class="mt-2 text-sm text-slate-700">{{ $dbStatus['message'] }}</p>
        </x-ui.card>

        <x-ui.card class="{{ $redisStatus['ok'] ? 'border-emerald-200/80' : 'border-red-200/80' }}">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-900">{{ __('admin.redis') }}</h2>
                <x-ui.badge :variant="$redisStatus['ok'] ? 'success' : 'danger'">
                    {{ $redisStatus['ok'] ? __('admin.status_ok') : __('admin.status_error') }}
                </x-ui.badge>
            </div>
            <p class="mt-2 text-sm text-slate-700">{{ $redisStatus['message'] }}</p>
        </x-ui.card>

        <x-ui.card class="{{ $storageStatus['ok'] ? 'border-emerald-200/80' : 'border-amber-200/80' }}">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-900">{{ __('admin.storage') }}</h2>
                <x-ui.badge :variant="$storageStatus['ok'] ? 'success' : 'warning'">
                    {{ $storageStatus['ok'] ? __('admin.status_ok') : __('admin.status_review') }}
                </x-ui.badge>
            </div>
            <ul class="mt-2 space-y-1 text-sm text-slate-700">
                <li>{{ __('admin.writable') }}: {{ $storageStatus['writable'] ? __('admin.yes') : __('admin.no') }}</li>
                <li>{{ __('admin.public_link') }}: {{ $storageStatus['public_link_ok'] ? __('admin.yes') : __('admin.no') }}</li>
                <li>{{ $storageStatus['message'] }}</li>
            </ul>
        </x-ui.card>

        <x-ui.card class="{{ $schedulerStatus['ok'] ? 'border-emerald-200/80' : 'border-amber-200/80' }}">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-900">{{ __('admin.scheduler') }}</h2>
                <x-ui.badge :variant="$schedulerStatus['ok'] ? 'success' : 'warning'">
                    {{ $schedulerStatus['ok'] ? __('admin.status_ok') : __('admin.status_stale') }}
                </x-ui.badge>
            </div>
            <p class="mt-2 text-sm text-slate-700">{{ $schedulerStatus['message'] }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ __('admin.last_run', ['last' => $schedulerStatus['last_run'] ?? __('admin.no_record'), 'source' => $schedulerStatus['source']]) }}</p>
        </x-ui.card>

        <x-ui.card class="{{ $queueStatus['ok'] ? 'border-emerald-200/80' : 'border-amber-200/80' }}">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-900">{{ __('admin.queue_worker') }}</h2>
                <x-ui.badge :variant="$queueStatus['ok'] ? 'success' : 'warning'">
                    {{ $queueStatus['ok'] ? __('admin.status_ok') : __('admin.status_review') }}
                </x-ui.badge>
            </div>
            <p class="mt-2 text-sm text-slate-700">{{ $queueStatus['message'] }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ __('admin.last_run', ['last' => $queueStatus['last_run'] ?? __('admin.no_record'), 'source' => $queueStatus['source']]) }}</p>
        </x-ui.card>

        <x-ui.card class="{{ $backupStatus['ok'] ? 'border-emerald-200/80' : 'border-amber-200/80' }}">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-900">{{ __('admin.backups') }}</h2>
                <x-ui.badge :variant="$backupStatus['ok'] ? 'success' : 'warning'">
                    {{ $backupStatus['ok'] ? __('admin.status_ok') : __('admin.status_review') }}
                </x-ui.badge>
            </div>
            <p class="mt-2 text-sm text-slate-700">{{ $backupStatus['message'] }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ __('admin.last_run', ['last' => $backupStatus['last_run'] ?? __('admin.no_record'), 'source' => $backupStatus['source']]) }}</p>
        </x-ui.card>
    </div>
</section>
