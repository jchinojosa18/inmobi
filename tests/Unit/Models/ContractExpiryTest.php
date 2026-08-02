<?php

namespace Tests\Unit\Models;

use App\Models\Contract;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContractExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function is_expiring_soon_true_when_ends_at_is_today(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');

        $contract = Contract::factory()->make([
            'status' => Contract::STATUS_ACTIVE,
            'ends_at' => '2026-08-01',
        ]);

        $this->assertTrue($contract->isExpiringSoon());
        $this->assertFalse($contract->isExpired());
        $this->assertSame(0, $contract->daysUntilEnd());
    }

    #[Test]
    public function is_expiring_soon_true_on_day_30(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');

        $contract = Contract::factory()->make([
            'status' => Contract::STATUS_ACTIVE,
            'ends_at' => '2026-08-31',
        ]);

        $this->assertTrue($contract->isExpiringSoon());
        $this->assertSame(30, $contract->daysUntilEnd());
    }

    #[Test]
    public function is_expiring_soon_false_on_day_31(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');

        $contract = Contract::factory()->make([
            'status' => Contract::STATUS_ACTIVE,
            'ends_at' => '2026-09-01',
        ]);

        $this->assertFalse($contract->isExpiringSoon());
        $this->assertSame(31, $contract->daysUntilEnd());
    }

    #[Test]
    public function is_expiring_soon_false_when_already_expired(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');

        $contract = Contract::factory()->make([
            'status' => Contract::STATUS_ACTIVE,
            'ends_at' => '2026-07-31',
        ]);

        $this->assertFalse($contract->isExpiringSoon());
        $this->assertTrue($contract->isExpired());
        $this->assertSame(-1, $contract->daysUntilEnd());
    }

    #[Test]
    public function is_expiring_soon_false_for_ended_or_null_ends_at(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');

        $ended = Contract::factory()->make([
            'status' => Contract::STATUS_ENDED,
            'ends_at' => '2026-08-15',
        ]);
        $open = Contract::factory()->make([
            'status' => Contract::STATUS_ACTIVE,
            'ends_at' => null,
        ]);

        $this->assertFalse($ended->isExpiringSoon());
        $this->assertFalse($open->isExpiringSoon());
        $this->assertNull($open->daysUntilEnd());
    }
}
