<?php

namespace Tests\Feature\Console;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CommandLockingTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_rent_command_skips_when_lock_is_taken(): void
    {
        $month = CarbonImmutable::now('America/Tijuana')->addMonths(6)->format('Y-m');
        $this->createTwoActiveContracts();

        $beforeCount = Charge::query()
            ->withoutOrganizationScope()
            ->where('type', Charge::TYPE_RENT)
            ->where('period', $month)
            ->count();

        $lock = Cache::lock('commands:inmo:generate-rent', 600);
        $this->assertTrue($lock->get());

        try {
            $this->artisan('inmo:generate-rent', [
                '--month' => $month,
            ])
                ->assertExitCode(0)
                ->expectsOutputToContain('skipped (locked)');

            $afterCount = Charge::query()
                ->withoutOrganizationScope()
                ->where('type', Charge::TYPE_RENT)
                ->where('period', $month)
                ->count();

            $this->assertSame($beforeCount, $afterCount);
        } finally {
            $lock->release();
        }
    }

    public function test_penalties_command_skips_when_lock_is_taken(): void
    {
        [$contract] = $this->createOverdueRentContract();

        $lock = Cache::lock('commands:inmo:penalties:run', 600);
        $this->assertTrue($lock->get());

        try {
            $this->artisan('inmo:penalties:run', [
                '--date' => '2026-03-04',
            ])
                ->assertExitCode(0)
                ->expectsOutputToContain('skipped (locked)');

            $this->assertSame(
                0,
                Charge::query()
                    ->withoutOrganizationScope()
                    ->where('organization_id', $contract->organization_id)
                    ->where('contract_id', $contract->id)
                    ->where('type', Charge::TYPE_PENALTY)
                    ->count()
            );
        } finally {
            $lock->release();
        }
    }

    public function test_daily_command_skips_when_lock_is_taken(): void
    {
        Queue::fake();

        $lock = Cache::lock('commands:inmo:daily', 600);
        $this->assertTrue($lock->get());

        try {
            $this->artisan('inmo:daily')
                ->assertExitCode(0)
                ->expectsOutputToContain('skipped (locked)');

            Queue::assertNothingPushed();
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{0: Contract, 1: Contract}
     */
    private function createTwoActiveContracts(): array
    {
        $organization = Organization::factory()->create();
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $unitA = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $unitB = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);

        $tenantA = Tenant::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $tenantB = Tenant::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $contractA = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unitA->id,
            'tenant_id' => $tenantA->id,
            'status' => Contract::STATUS_ACTIVE,
            'starts_at' => '2025-12-01',
            'ends_at' => null,
            'due_day' => 5,
            'grace_days' => 5,
            'rent_amount' => 12000,
        ]);

        $contractB = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unitB->id,
            'tenant_id' => $tenantB->id,
            'status' => Contract::STATUS_ACTIVE,
            'starts_at' => '2026-01-10',
            'ends_at' => null,
            'due_day' => 15,
            'grace_days' => 2,
            'rent_amount' => 8500,
        ]);

        return [$contractA, $contractB];
    }

    /**
     * @return array{0: Contract, 1: Charge}
     */
    private function createOverdueRentContract(): array
    {
        $organization = Organization::factory()->create();

        $property = Property::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);

        $tenant = Tenant::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $contract = Contract::factory()->ended()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'penalty_rate_daily' => 0.01,
        ]);

        $rentCharge = Charge::query()
            ->withoutOrganizationScope()
            ->create([
                'organization_id' => $organization->id,
                'contract_id' => $contract->id,
                'unit_id' => $unit->id,
                'type' => Charge::TYPE_RENT,
                'period' => '2026-03',
                'charge_date' => '2026-03-01',
                'due_date' => '2026-03-01',
                'grace_until' => '2026-03-01',
                'amount' => 1000,
                'meta' => [],
            ]);

        return [$contract, $rentCharge];
    }
}
