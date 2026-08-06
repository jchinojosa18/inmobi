<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DailyOperationsJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(public array $context = []) {}

    /**
     * Create a new job instance.
     */
    public function handle(): void
    {
        Log::info('DailyOperationsJob handled (placeholder)', [
            'context' => $this->context,
            'handled_at' => now()->toDateTimeString(),
        ]);
    }
}
