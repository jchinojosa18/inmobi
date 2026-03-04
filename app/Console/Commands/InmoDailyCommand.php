<?php

namespace App\Console\Commands;

use App\Jobs\DailyOperationsJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class InmoDailyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inmo:daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs daily inmo maintenance placeholders (no business logic yet)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Log::info('inmo:daily started', [
            'run_at' => now()->toDateTimeString(),
        ]);

        DailyOperationsJob::dispatch([
            'source' => 'inmo:daily',
            'triggered_at' => now()->toIso8601String(),
        ])->onQueue('default');

        Log::info('inmo:daily finished', [
            'queued_job' => DailyOperationsJob::class,
        ]);

        $this->info('inmo:daily executed. DailyOperationsJob dispatched.');

        return self::SUCCESS;
    }
}
