<?php

namespace App\Support;

use App\Models\SystemHeartbeat;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

class SystemHeartbeatService
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function touch(string $key, string $status = 'ok', array $meta = []): void
    {
        if (! $this->tableExists()) {
            return;
        }

        SystemHeartbeat::query()->updateOrCreate(
            ['key' => $key],
            [
                'last_ran_at' => now(),
                'status' => $status,
                'meta' => $meta,
            ]
        );
    }

    public function get(string $key): ?SystemHeartbeat
    {
        if (! $this->tableExists()) {
            return null;
        }

        return SystemHeartbeat::query()
            ->where('key', $key)
            ->first();
    }

    public function isFresh(?SystemHeartbeat $heartbeat, int $maxAgeMinutes): bool
    {
        if ($heartbeat?->last_ran_at === null) {
            return false;
        }

        $threshold = CarbonImmutable::now()->subMinutes($maxAgeMinutes);

        return $heartbeat->last_ran_at->greaterThanOrEqualTo($threshold);
    }

    private function tableExists(): bool
    {
        try {
            return Schema::hasTable('system_heartbeats');
        } catch (\Throwable) {
            return false;
        }
    }
}
