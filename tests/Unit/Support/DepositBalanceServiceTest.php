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

class DepositBalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_out_zeros_available_deposit(): void
    {
        $contract = $this->makeContract(depositAmount: 9500.0);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 9500,
        ]);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_TRANSFER_OUT,
            'period' => '2026-08',
            'charge_date' => '2026-08-01',
            'amount' => -9500,
        ]);

        $service = app(DepositBalanceService::class);

        $this->assertSame(9500.0, $service->transferredOutDepositAmount($contract));
        $this->assertSame(0.0, $service->availableDepositAmount($contract));
    }

    public function test_outstanding_ignores_transfer_out_and_keeps_rent_debt(): void
    {
        $contract = $this->makeContract(depositAmount: 9500.0, rentAmount: 1000.0);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_TRANSFER_OUT,
            'period' => '2026-08',
            'charge_date' => '2026-08-01',
            'amount' => -9500,
        ]);

        $service = app(DepositBalanceService::class);

        $this->assertSame(1000.0, $service->outstandingBalanceExcludingDepositHold($contract));
    }

    private function makeContract(float $depositAmount, float $rentAmount = 0): Contract
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
            'rent_amount' => $rentAmount,
        ]);
    }
}
