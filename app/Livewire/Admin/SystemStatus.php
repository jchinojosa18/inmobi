<?php

namespace App\Livewire\Admin;

use App\Support\SystemHeartbeatService;
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
        if (! auth()->user()?->hasRole('Admin')) {
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
            'title' => 'Admin System',
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
                'message' => 'Conexión DB operativa ('.config('database.default').').',
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'message' => 'DB error: '.$exception->getMessage(),
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
                    'message' => 'Redis responde ping correctamente.',
                ];
            }

            return [
                'ok' => false,
                'message' => 'Redis respondió inesperado: '.(string) $response,
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'message' => 'Redis error: '.$exception->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok:bool,writable:bool,public_link_ok:bool,message:string}
     */
    private function checkStorage(): array
    {
        $disk = (string) config('filesystems.documents_disk', 'public');
        $writable = false;
        $publicLinkOk = false;
        $message = 'OK';

        try {
            $probeFile = 'healthchecks/system-'.Str::uuid().'.txt';
            Storage::disk($disk)->put($probeFile, now()->toIso8601String());
            $writable = Storage::disk($disk)->exists($probeFile);
            Storage::disk($disk)->delete($probeFile);

            if (! $writable) {
                $message = "No fue posible confirmar escritura en disk {$disk}.";
            }
        } catch (\Throwable $exception) {
            $message = 'Storage error: '.$exception->getMessage();
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
        $lastRun = $heartbeat?->last_ran_at?->toDateTimeString();

        if ($heartbeatService->isFresh($heartbeat, 5)) {
            return [
                'ok' => true,
                'message' => 'Scheduler heartbeat reciente (<= 5 min).',
                'last_run' => $lastRun,
                'source' => 'system_heartbeats',
            ];
        }

        return [
            'ok' => false,
            'message' => 'Sin corrida reciente del scheduler.',
            'last_run' => $lastRun,
            'source' => 'system_heartbeats',
        ];
    }

    /**
     * @return array{ok:bool,message:string,last_run:?string,source:string}
     */
    private function checkQueueWorker(SystemHeartbeatService $heartbeatService): array
    {
        $heartbeat = $heartbeatService->get('queue_worker');
        $lastRun = $heartbeat?->last_ran_at?->toDateTimeString();

        if ($heartbeatService->isFresh($heartbeat, 30) && $heartbeat?->status === 'ok') {
            return [
                'ok' => true,
                'message' => 'Queue worker activo (heartbeat <= 30 min).',
                'last_run' => $lastRun,
                'source' => 'system_heartbeats',
            ];
        }

        if ($heartbeat?->status === 'failed') {
            return [
                'ok' => false,
                'message' => 'Último heartbeat de queue reportó failure.',
                'last_run' => $lastRun,
                'source' => 'system_heartbeats',
            ];
        }

        return [
            'ok' => false,
            'message' => 'Sin actividad reciente de queue worker.',
            'last_run' => $lastRun,
            'source' => 'system_heartbeats',
        ];
    }

    /**
     * @return array{ok:bool,message:string,last_run:?string,source:string}
     */
    private function checkBackup(SystemHeartbeatService $heartbeatService): array
    {
        $heartbeat = $heartbeatService->get('backup');
        $lastRun = $heartbeat?->last_ran_at?->toDateTimeString();

        if ($heartbeat !== null && $heartbeat->status === 'ok') {
            return [
                'ok' => true,
                'message' => 'Último backup ejecutado correctamente.',
                'last_run' => $lastRun,
                'source' => 'system_heartbeats',
            ];
        }

        if ($heartbeat !== null) {
            return [
                'ok' => false,
                'message' => 'Último backup con warning/error.',
                'last_run' => $lastRun,
                'source' => 'system_heartbeats',
            ];
        }

        $logPath = storage_path('logs/laravel.log');
        $logLastRun = is_file($logPath) ? date('Y-m-d H:i:s', (int) filemtime($logPath)) : null;

        return [
            'ok' => false,
            'message' => 'Sin heartbeat de backup; revisa logs para detalle.',
            'last_run' => $logLastRun,
            'source' => 'log',
        ];
    }
}
