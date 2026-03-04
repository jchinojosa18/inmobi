<?php

namespace App\Providers;

use App\Support\SystemHeartbeatService;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Queue::after(function (JobProcessed $event): void {
            try {
                app(SystemHeartbeatService::class)->touch('queue_worker', 'ok', [
                    'connection' => $event->connectionName,
                    'job' => $event->job->resolveName(),
                    'queue' => $event->job->getQueue(),
                ]);
            } catch (\Throwable) {
                // Never block queue worker due to heartbeat instrumentation.
            }
        });

        Queue::failing(function (JobFailed $event): void {
            try {
                app(SystemHeartbeatService::class)->touch('queue_worker', 'failed', [
                    'connection' => $event->connectionName,
                    'job' => $event->job->resolveName(),
                    'queue' => $event->job->getQueue(),
                    'exception' => $event->exception->getMessage(),
                ]);
            } catch (\Throwable) {
                // Never block queue worker due to heartbeat instrumentation.
            }
        });
    }
}
