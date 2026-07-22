<?php

namespace Tests\Unit\Actions;

use App\Actions\Contracts\RegisterDepositHoldAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RegisterDepositHoldActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();
    }

    public function test_partial_holds_are_allowed_until_deposit_amount_is_reached(): void
    {
        $contract = $this->makeContract(depositAmount: 1000.0);
        $action = app(RegisterDepositHoldAction::class);

        $action->execute($contract, 400.0, '2026-03-01', 'parcial 1', null);
        $action->execute($contract, 600.0, '2026-03-02', 'parcial 2', null);

        $this->assertSame(2, Charge::query()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_DEPOSIT_HOLD)
            ->count());

        $sum = (float) Charge::query()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_DEPOSIT_HOLD)
            ->sum('amount');

        $this->assertSame(1000.0, $sum);
    }

    public function test_amount_exceeding_remaining_is_rejected(): void
    {
        $contract = $this->makeContract(depositAmount: 1000.0);
        $action = app(RegisterDepositHoldAction::class);

        $action->execute($contract, 700.0, '2026-03-01', null, null);

        try {
            $action->execute($contract, 400.0, '2026-03-02', null, null);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('deposit_amount', $exception->errors());
        }

        $this->assertSame(1, Charge::query()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_DEPOSIT_HOLD)
            ->count());
    }

    public function test_registration_is_rejected_when_deposit_already_complete(): void
    {
        $contract = $this->makeContract(depositAmount: 500.0);
        $action = app(RegisterDepositHoldAction::class);

        $action->execute($contract, 500.0, '2026-03-01', null, null);

        $this->expectException(ValidationException::class);
        $action->execute($contract, 100.0, '2026-03-02', null, null);
    }

    public function test_idempotent_same_date_and_amount_does_not_create_duplicate(): void
    {
        $contract = $this->makeContract(depositAmount: 1000.0);
        $action = app(RegisterDepositHoldAction::class);

        $first = $action->execute($contract, 400.0, '2026-03-01', 'a', null);
        $second = $action->execute($contract, 400.0, '2026-03-01', 'b', null);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Charge::query()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_DEPOSIT_HOLD)
            ->count());
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

        TenantContext::setOrganizationId($organization->id);

        return Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'deposit_amount' => $depositAmount,
            'rent_amount' => 0,
        ]);
    }
}
