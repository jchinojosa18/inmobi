<?php

namespace Tests\Feature\Console;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RebuildPenaltiesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rebuilds_penalties_for_contract_in_date_range(): void
    {
        [$contract] = $this->createOverdueRentContract();

        $this->artisan('inmo:penalties:run', [
            '--date' => '2026-03-04',
            '--from-date' => '2026-03-02',
        ])->assertExitCode(0);

        $this->assertSame(
            3,
            Charge::query()
                ->withoutOrganizationScope()
                ->where('contract_id', $contract->id)
                ->where('type', Charge::TYPE_PENALTY)
                ->count()
        );

        $this->artisan('inmo:penalties:rebuild', [
            '--contract' => $contract->id,
            '--from' => '2026-03-02',
            '--to' => '2026-03-04',
        ])->assertExitCode(0)
            ->expectsOutputToContain('Penalties borradas: 3')
            ->expectsOutputToContain('Penalties creadas: 3');

        $penaltyDates = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_PENALTY)
            ->orderBy('penalty_date')
            ->pluck('penalty_date')
            ->map(fn ($date) => $date?->format('Y-m-d'))
            ->all();

        $this->assertSame(['2026-03-02', '2026-03-03', '2026-03-04'], $penaltyDates);
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
