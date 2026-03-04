<?php

namespace Tests\Unit\Actions;

use App\Actions\Payments\ApplyPaymentAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplyPaymentActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_it_supports_partial_payment_allocation(): void
    {
        [$organization, $contract, $unit] = $this->makeContractGraph();
        TenantContext::setOrganizationId($organization->id);

        $rentCharge = Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_RENT,
            'amount' => 1000,
            'charge_date' => '2026-01-05',
            'meta' => null,
        ]);

        $payment = Payment::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'amount' => 600,
        ]);

        $result = app(ApplyPaymentAction::class)->execute($contract, $payment);

        $this->assertFalse($result->idempotent);
        $this->assertSame(600.0, $result->allocatedAmount);
        $this->assertSame(0.0, $result->creditedAmount);
        $this->assertSame(1, $result->allocationsCount);

        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $payment->id,
            'charge_id' => $rentCharge->id,
            'amount' => '600.00',
        ]);

        $this->assertSame(Charge::STATUS_PARTIAL, $rentCharge->fresh()->status);
    }

    public function test_it_registers_credit_balance_when_payment_exceeds_pending_charges(): void
    {
        [$organization, $contract, $unit] = $this->makeContractGraph();
        TenantContext::setOrganizationId($organization->id);

        $rentCharge = Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_RENT,
            'amount' => 500,
            'charge_date' => '2026-01-05',
            'meta' => null,
        ]);

        $payment = Payment::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'amount' => 800,
        ]);

        $action = app(ApplyPaymentAction::class);

        $result = $action->execute($contract, $payment);

        $this->assertFalse($result->idempotent);
        $this->assertSame(500.0, $result->allocatedAmount);
        $this->assertSame(300.0, $result->creditedAmount);

        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $payment->id,
            'charge_id' => $rentCharge->id,
            'amount' => '500.00',
        ]);

        $this->assertDatabaseHas('credit_balances', [
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'balance' => '300.00',
            'last_payment_id' => $payment->id,
        ]);

        $idempotentResult = $action->execute($contract, $payment);

        $this->assertTrue($idempotentResult->idempotent);
        $this->assertSame(500.0, $idempotentResult->allocatedAmount);
        $this->assertSame(300.0, $idempotentResult->creditedAmount);
        $this->assertSame(1, $idempotentResult->allocationsCount);
        $this->assertSame(1, PaymentAllocation::query()->where('payment_id', $payment->id)->count());
        $this->assertSame(
            300.0,
            (float) CreditBalance::query()->where('contract_id', $contract->id)->value('balance')
        );
    }

    public function test_it_prioritizes_rent_before_penalty(): void
    {
        [$organization, $contract, $unit] = $this->makeContractGraph();
        TenantContext::setOrganizationId($organization->id);

        $penaltyCharge = Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_PENALTY,
            'amount' => 300,
            'charge_date' => '2026-01-01',
            'meta' => null,
        ]);

        $rentCharge = Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_RENT,
            'amount' => 300,
            'charge_date' => '2026-02-01',
            'meta' => null,
        ]);

        $payment = Payment::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'amount' => 300,
        ]);

        $result = app(ApplyPaymentAction::class)->execute($contract, $payment);

        $this->assertFalse($result->idempotent);
        $this->assertSame(300.0, $result->allocatedAmount);
        $this->assertSame(0.0, $result->creditedAmount);
        $this->assertSame(1, $result->allocationsCount);

        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $payment->id,
            'charge_id' => $rentCharge->id,
            'amount' => '300.00',
        ]);

        $this->assertDatabaseMissing('payment_allocations', [
            'payment_id' => $payment->id,
            'charge_id' => $penaltyCharge->id,
        ]);
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
