<?php

namespace App\Jobs;

use App\Support\SystemHeartbeatService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Daily ops health pulse (not business logic).
 *
 * Rent generation and penalties run via dedicated scheduled commands
 * (`inmo:generate-rent`, `inmo:penalties:run`). This job only records that
 * the daily maintenance pipeline woke up, for /admin/system visibility.
 */
class DailyOperationsJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(public array $context = []) {}

    public function handle(SystemHeartbeatService $heartbeatService): void
    {
        $heartbeatService->touch('daily_operations', 'ok', [
            'source' => (string) ($this->context['source'] ?? 'DailyOperationsJob'),
            'triggered_at' => $this->context['triggered_at'] ?? now()->toIso8601String(),
        ]);

        Log::info('DailyOperationsJob heartbeat recorded', [
            'context' => $this->context,
            'handled_at' => now()->toDateTimeString(),
        ]);
    }
}
