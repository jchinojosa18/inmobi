<?php

namespace Tests\Unit\Support;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\DepositBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepositBalanceServiceDepositHoldCapTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_and_remaining_deposit_hold_amounts(): void
    {
        $contract = $this->makeContract(depositAmount: 1000.0);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 400,
        ]);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-03',
            'charge_date' => '2026-03-05',
            'amount' => 250,
        ]);

        $service = app(DepositBalanceService::class);

        $this->assertSame(650.0, $service->registeredDepositHoldAmount($contract));
        $this->assertSame(350.0, $service->remainingDepositHoldAmount($contract));
    }

    public function test_remaining_is_zero_when_registered_meets_or_exceeds_deposit_amount(): void
    {
        $contract = $this->makeContract(depositAmount: 500.0);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 500,
        ]);

        $service = app(DepositBalanceService::class);

        $this->assertSame(500.0, $service->registeredDepositHoldAmount($contract));
        $this->assertSame(0.0, $service->remainingDepositHoldAmount($contract));
    }

    private function makeContract(float $depositAmount): Contract
    {
        $organization = Organization::factory()->create();
        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $tenant = Tenant::factory()->create(['organization_id' => $organization->id]);

        return Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'deposit_amount' => $depositAmount,
            'rent_amount' => 0,
        ]);
    }
}
