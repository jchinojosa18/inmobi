<?php

namespace Tests\Unit\Actions;

use App\Actions\Payments\ApplyCreditBalanceAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplyCreditBalanceActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_it_applies_credit_to_pending_rent_and_decrements_balance(): void
    {
        [$organization, $contract, $unit] = $this->makeContractGraph();
        TenantContext::setOrganizationId($organization->id);

        Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_RENT,
            'amount' => 1000,
            'charge_date' => '2026-01-05',
        ]);

        CreditBalance::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'balance' => 400,
        ]);

        $result = app(ApplyCreditBalanceAction::class)->execute($contract);

        $this->assertSame(400.0, $result->appliedAmount);
        $this->assertSame(1, $result->allocationsCount);
        $this->assertNotNull($result->paymentId);

        $this->assertDatabaseHas('payments', [
            'id' => $result->paymentId,
            'method' => Payment::METHOD_CREDIT,
            'amount' => '400.00',
            'receipt_folio' => null,
        ]);
        $this->assertSame(0.0, (float) CreditBalance::query()->where('contract_id', $contract->id)->value('balance'));
    }

    public function test_it_is_noop_when_credit_is_zero(): void
    {
        [$organization, $contract] = $this->makeContractGraph();
        TenantContext::setOrganizationId($organization->id);

        $result = app(ApplyCreditBalanceAction::class)->execute($contract);

        $this->assertSame(0.0, $result->appliedAmount);
        $this->assertNull($result->paymentId);
        $this->assertDatabaseCount('payments', 0);
    }

    /**
     * @return array{0: Organization, 1: Contract, 2: Unit}
     */
    private function makeContractGraph(): array
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
        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => Contract::STATUS_ENDED,
            'ends_at' => '2026-12-31',
        ]);

        return [$organization, $contract, $unit];
    }
}
