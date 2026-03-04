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
use Tests\TestCase;

class GenerateRentChargesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_rent_charges_for_two_active_contracts(): void
    {
        CarbonImmutable::setTestNow('2026-02-15 10:00:00');
        $month = '2026-03';

        [$contractA, $contractB] = $this->createTwoActiveContracts();

        $this->artisan('inmo:generate-rent', [
            '--month' => $month,
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('Cargos creados: 2');

        $charges = Charge::query()
            ->withoutOrganizationScope()
            ->where('type', Charge::TYPE_RENT)
            ->where('period', $month)
            ->get()
            ->keyBy('contract_id');

        $this->assertCount(2, $charges);

        $chargeA = $charges->get($contractA->id);
        $this->assertNotNull($chargeA);
        $this->assertSame('2026-03-01', $chargeA->charge_date?->format('Y-m-d'));
        $this->assertSame('2026-03-05', $chargeA->due_date?->format('Y-m-d'));
        $this->assertSame('2026-03-10', $chargeA->grace_until?->format('Y-m-d'));

        $chargeB = $charges->get($contractB->id);
        $this->assertNotNull($chargeB);
        $this->assertSame('2026-03-01', $chargeB->charge_date?->format('Y-m-d'));
        $this->assertSame('2026-03-15', $chargeB->due_date?->format('Y-m-d'));
        $this->assertSame('2026-03-17', $chargeB->grace_until?->format('Y-m-d'));

        CarbonImmutable::setTestNow();
    }

    public function test_it_is_idempotent_when_command_runs_twice_for_same_month(): void
    {
        $month = '2026-04';

        $this->createTwoActiveContracts();

        $this->artisan('inmo:generate-rent', [
            '--month' => $month,
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('Cargos creados: 2');

        $this->artisan('inmo:generate-rent', [
            '--month' => $month,
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('Cargos creados: 0')
            ->expectsOutputToContain('Cargos omitidos (ya existentes): 2');

        $this->assertSame(
            2,
            Charge::query()
                ->withoutOrganizationScope()
                ->where('type', Charge::TYPE_RENT)
                ->where('period', $month)
                ->count()
        );
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
}
