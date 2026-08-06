<?php

namespace Tests\Unit\Jobs;

use App\Jobs\DailyOperationsJob;
use App\Models\SystemHeartbeat;
use App\Support\SystemHeartbeatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyOperationsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_daily_operations_heartbeat(): void
    {
        (new DailyOperationsJob([
            'source' => 'inmo:daily',
            'triggered_at' => '2026-03-10T00:15:00-08:00',
        ]))->handle(app(SystemHeartbeatService::class));

        $heartbeat = SystemHeartbeat::query()->where('key', 'daily_operations')->first();

        $this->assertNotNull($heartbeat);
        $this->assertSame('ok', $heartbeat->status);
        $this->assertSame('inmo:daily', data_get($heartbeat->meta, 'source'));
    }
}
