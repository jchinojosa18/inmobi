<?php

namespace Tests\Feature\Contracts;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractRentAutogenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_contract_creation_generates_current_month_rent_charge(): void
    {
        CarbonImmutable::setTestNow('2026-03-15 09:00:00');

        [$organization, $unit, $tenant] = $this->createContractGraph();

        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => Contract::STATUS_ACTIVE,
            'rent_amount' => 12500,
            'due_day' => 8,
            'grace_days' => 5,
        ]);

        $charge = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_RENT)
            ->where('period', '2026-03')
            ->first();

        $this->assertNotNull($charge);
        $this->assertSame('2026-03-01', $charge->charge_date?->format('Y-m-d'));
        $this->assertSame('2026-03-08', $charge->due_date?->format('Y-m-d'));
        $this->assertSame('2026-03-13', $charge->grace_until?->format('Y-m-d'));
        $this->assertSame('12500.00', $charge->amount);

        CarbonImmutable::setTestNow();
    }

    public function test_contract_activation_generates_current_month_rent_charge_once(): void
    {
        CarbonImmutable::setTestNow('2026-03-15 09:00:00');

        [$organization, $unit, $tenant] = $this->createContractGraph();

        $contract = Contract::factory()->ended()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'rent_amount' => 8000,
            'due_day' => 5,
            'grace_days' => 2,
        ]);

        $this->assertSame(
            0,
            Charge::query()
                ->withoutOrganizationScope()
                ->where('contract_id', $contract->id)
                ->where('type', Charge::TYPE_RENT)
                ->where('period', '2026-03')
                ->count()
        );

        $contract->update([
            'status' => Contract::STATUS_ACTIVE,
            'ends_at' => null,
        ]);

        $contract->update([
            'status' => Contract::STATUS_ACTIVE,
        ]);

        $this->assertSame(
            1,
            Charge::query()
                ->withoutOrganizationScope()
                ->where('contract_id', $contract->id)
                ->where('type', Charge::TYPE_RENT)
                ->where('period', '2026-03')
                ->count()
        );

        CarbonImmutable::setTestNow();
    }

    /**
     * @return array{Organization, Unit, Tenant}
     */
    private function createContractGraph(): array
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

        return [$organization, $unit, $tenant];
    }
}
