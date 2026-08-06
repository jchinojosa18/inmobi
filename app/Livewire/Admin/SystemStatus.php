<?php

namespace App\Livewire\Admin;

use App\Support\DateDisplay;
use App\Support\SystemHeartbeatService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;

class SystemStatus extends Component
{
    public function mount(): void
    {
        if (! (auth()->user()?->can('system.view') ?? false)) {
            abort(403);
        }
    }

    public function render(SystemHeartbeatService $heartbeatService): View
    {
        $appStatus = [
            'app_env' => (string) config('app.env'),
            'app_debug' => config('app.debug') ? 'true' : 'false',
            'php_version' => PHP_VERSION,
        ];

        $dbStatus = $this->checkDatabase();
        $redisStatus = $this->checkRedis();
        $storageStatus = $this->checkStorage();
        $schedulerStatus = $this->checkScheduler($heartbeatService);
        $queueStatus = $this->checkQueueWorker($heartbeatService);
        $backupStatus = $this->checkBackup($heartbeatService);

        return view('livewire.admin.system-status', [
            'appStatus' => $appStatus,
            'dbStatus' => $dbStatus,
            'redisStatus' => $redisStatus,
            'storageStatus' => $storageStatus,
            'schedulerStatus' => $schedulerStatus,
            'queueStatus' => $queueStatus,
            'backupStatus' => $backupStatus,
        ])->layout('layouts.app', [
            'title' => __('admin.system_title'),
        ]);
    }

    /**
     * @return array{ok:bool,message:string}
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            DB::select('select 1');

            return [
                'ok' => true,
                'message' => __('admin.db_ok', ['driver' => config('database.default')]),
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'message' => __('admin.db_error', ['message' => $exception->getMessage()]),
            ];
        }
    }

    /**
     * @return array{ok:bool,message:string}
     */
    private function checkRedis(): array
    {
        try {
            $response = Redis::connection()->ping();
            $normalized = strtolower(trim((string) $response, '+'));

            if ($normalized === 'pong' || $normalized === '1') {
                return [
                    'ok' => true,
                    'message' => __('admin.redis_ok'),
                ];
            }

            return [
                'ok' => false,
                'message' => __('admin.redis_unexpected', ['response' => (string) $response]),
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'message' => __('admin.redis_error', ['message' => $exception->getMessage()]),
            ];
        }
    }

    /**
     * @return array{ok:bool,writable:bool,public_link_ok:bool,message:string}
     */
    private function checkStorage(): array
    {
        $disk = (string) config('filesystems.documents_disk', 'local');
        $writable = false;
        $publicLinkOk = false;
        $message = __('admin.status_ok');

        try {
            $probeFile = 'healthchecks/system-'.Str::uuid().'.txt';
            Storage::disk($disk)->put($probeFile, now()->toIso8601String());
            $writable = Storage::disk($disk)->exists($probeFile);
            Storage::disk($disk)->delete($probeFile);

            if (! $writable) {
                $message = __('admin.storage_write_failed', ['disk' => $disk]);
            }
        } catch (\Throwable $exception) {
            $message = __('admin.storage_error', ['message' => $exception->getMessage()]);
        }

        $publicStoragePath = public_path('storage');
        $expectedTarget = storage_path('app/public');
        $realPublicPath = realpath($publicStoragePath);
        $realExpectedTarget = realpath($expectedTarget);

        if ($realPublicPath !== false && $realExpectedTarget !== false && $realPublicPath === $realExpectedTarget) {
            $publicLinkOk = true;
        }

        return [
            'ok' => $writable && $publicLinkOk,
            'writable' => $writable,
            'public_link_ok' => $publicLinkOk,
            'message' => $message,
        ];
    }

    /**
     * @return array{ok:bool,message:string,last_run:?string,source:string}
     */
    private function checkScheduler(SystemHeartbeatService $heartbeatService): array
    {
        $heartbeat = $heartbeatService->get('scheduler');
        $lastRun = $this->formatHeartbeatLastRun($heartbeat?->last_ran_at);

        if ($heartbeatService->isFresh($heartbeat, 5)) {
            return [
                'ok' => true,
                'message' => __('admin.scheduler_ok'),
                'last_run' => $lastRun,
                'source' => __('admin.heartbeat_source'),
            ];
        }

        return [
            'ok' => false,
            'message' => __('admin.scheduler_stale'),
            'last_run' => $lastRun,
            'source' => __('admin.heartbeat_source'),
        ];
    }

    /**
     * @return array{ok:bool,message:string,last_run:?string,source:string}
     */
    private function checkQueueWorker(SystemHeartbeatService $heartbeatService): array
    {
        $heartbeat = $heartbeatService->get('queue_worker');
        $lastRun = $this->formatHeartbeatLastRun($heartbeat?->last_ran_at);

        if ($heartbeatService->isFresh($heartbeat, 30) && $heartbeat?->status === 'ok') {
            return [
                'ok' => true,
                'message' => __('admin.queue_ok'),
                'last_run' => $lastRun,
                'source' => __('admin.heartbeat_source'),
            ];
        }

        if ($heartbeat?->status === 'failed') {
            return [
                'ok' => false,
                'message' => __('admin.queue_failed'),
                'last_run' => $lastRun,
                'source' => __('admin.heartbeat_source'),
            ];
        }

        return [
            'ok' => false,
            'message' => __('admin.queue_stale'),
            'last_run' => $lastRun,
            'source' => __('admin.heartbeat_source'),
        ];
    }

    /**
     * @return array{ok:bool,message:string,last_run:?string,source:string}
     */
    private function checkBackup(SystemHeartbeatService $heartbeatService): array
    {
        $heartbeat = $heartbeatService->get('backup');
        $lastRun = $this->formatHeartbeatLastRun($heartbeat?->last_ran_at);

        if ($heartbeat !== null && $heartbeat->status === 'ok') {
            return [
                'ok' => true,
                'message' => __('admin.backup_ok'),
                'last_run' => $lastRun,
                'source' => __('admin.heartbeat_source'),
            ];
        }

        if ($heartbeat !== null) {
            return [
                'ok' => false,
                'message' => __('admin.backup_warning'),
                'last_run' => $lastRun,
                'source' => __('admin.heartbeat_source'),
            ];
        }

        $logPath = storage_path('logs/laravel.log');
        $logLastRun = is_file($logPath)
            ? DateDisplay::formatDateTime(CarbonImmutable::createFromTimestamp((int) filemtime($logPath)))
            : null;

        return [
            'ok' => false,
            'message' => __('admin.backup_no_heartbeat'),
            'last_run' => $logLastRun,
            'source' => __('admin.log_source'),
        ];
    }

    private function formatHeartbeatLastRun(mixed $lastRanAt): ?string
    {
        if ($lastRanAt === null) {
            return null;
        }

        return DateDisplay::formatDateTime($lastRanAt);
    }
}
