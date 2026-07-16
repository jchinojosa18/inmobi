<?php

namespace Tests\Unit\Actions;

use App\Actions\Contracts\ProcessContractSettlementAction;
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
use RuntimeException;
use Tests\TestCase;

class ProcessContractSettlementActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_deposit_covers_all_and_generates_refund_expense(): void
    {
        [$contract, $depositHold] = $this->createContractWithDepositHold(1000);

        $payment = Payment::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'paid_at' => '2026-03-01 10:00:00',
            'amount' => 1000,
        ]);

        PaymentAllocation::factory()->create([
            'organization_id' => $contract->organization_id,
            'payment_id' => $payment->id,
            'charge_id' => $depositHold->id,
            'amount' => 1000,
        ]);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_RENT,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 300,
        ]);

        $result = app(ProcessContractSettlementAction::class)->execute(
            contract: $contract,
            moveOutDate: '2026-03-20',
            concepts: [
                ['description' => 'Limpieza final', 'amount' => 200],
            ],
            userId: null,
        );

        $this->assertSame(0.0, $result->balanceToCollect);
        $this->assertSame(500.0, $result->depositRefund);

        $this->assertDatabaseHas('charges', [
            'contract_id' => $contract->id,
            'type' => Charge::TYPE_DEPOSIT_APPLY,
            'amount' => -500,
        ]);

        $this->assertDatabaseHas('expenses', [
            'organization_id' => $contract->organization_id,
            'category' => 'Refund deposit',
            'amount' => 500,
        ]);

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'status' => Contract::STATUS_ENDED,
            'ends_at' => '2026-03-20 00:00:00',
        ]);
    }

    public function test_deposit_partial_leaves_balance_to_collect(): void
    {
        [$contract, $depositHold] = $this->createContractWithDepositHold(300);

        $payment = Payment::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'paid_at' => '2026-03-01 10:00:00',
            'amount' => 300,
        ]);

        PaymentAllocation::factory()->create([
            'organization_id' => $contract->organization_id,
            'payment_id' => $payment->id,
            'charge_id' => $depositHold->id,
            'amount' => 300,
        ]);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_RENT,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 400,
        ]);

        $result = app(ProcessContractSettlementAction::class)->execute(
            contract: $contract,
            moveOutDate: '2026-03-20',
            concepts: [
                ['description' => 'Daño pared', 'amount' => 200],
            ],
            userId: null,
        );

        $this->assertSame(300.0, $result->depositApplied);
        $this->assertSame(0.0, $result->depositRefund);
        $this->assertSame(300.0, $result->balanceToCollect);

        $this->assertDatabaseHas('charges', [
            'contract_id' => $contract->id,
            'type' => Charge::TYPE_DEPOSIT_APPLY,
            'amount' => -300,
        ]);

        $this->assertDatabaseMissing('expenses', [
            'organization_id' => $contract->organization_id,
            'category' => 'Refund deposit',
        ]);
    }

    public function test_second_execution_on_ended_contract_throws_exception(): void
    {
        [$contract] = $this->createContractWithDepositHold(500);

        app(ProcessContractSettlementAction::class)->execute(
            contract: $contract,
            moveOutDate: '2026-03-20',
            concepts: [
                ['description' => 'Limpieza final', 'amount' => 100],
            ],
            userId: null,
        );

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'status' => Contract::STATUS_ENDED,
        ]);

        $this->expectException(RuntimeException::class);

        app(ProcessContractSettlementAction::class)->execute(
            contract: $contract->refresh(),
            moveOutDate: '2026-03-21',
            concepts: [
                ['description' => 'Segundo intento', 'amount' => 50],
            ],
            userId: null,
        );
    }

    public function test_credit_balance_covers_outstanding_before_deposit_is_applied(): void
    {
        [$contract, $depositHold] = $this->createContractWithDepositHold(1000);

        $depositPayment = Payment::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'paid_at' => '2026-03-01 10:00:00',
            'amount' => 1000,
        ]);

        PaymentAllocation::factory()->create([
            'organization_id' => $contract->organization_id,
            'payment_id' => $depositPayment->id,
            'charge_id' => $depositHold->id,
            'amount' => 1000,
        ]);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_RENT,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 300,
        ]);

        CreditBalance::query()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'balance' => 300,
        ]);

        $result = app(ProcessContractSettlementAction::class)->execute(
            contract: $contract,
            moveOutDate: '2026-03-20',
            concepts: [
                ['description' => 'Limpieza final', 'amount' => 200],
            ],
            userId: null,
        );

        // Credit fully covers the pre-existing rent (300); only the new moveout
        // concept (200) remains outstanding for the deposit to cover.
        $this->assertSame(200.0, $result->outstandingBeforeDeposit);
        $this->assertSame(200.0, $result->depositApplied);
        $this->assertSame(800.0, $result->depositRefund);
        $this->assertSame(0.0, $result->balanceToCollect);

        $this->assertDatabaseHas('payments', [
            'contract_id' => $contract->id,
            'method' => Payment::METHOD_CREDIT,
            'amount' => '300.00',
        ]);

        $this->assertDatabaseHas('charges', [
            'contract_id' => $contract->id,
            'type' => Charge::TYPE_DEPOSIT_APPLY,
            'amount' => -200,
        ]);

        $this->assertSame(
            0.0,
            (float) CreditBalance::query()->withoutOrganizationScope()->where('contract_id', $contract->id)->value('balance')
        );
    }

    /**
     * @return array{0: Contract, 1: Charge}
     */
    private function createContractWithDepositHold(float $depositAmount): array
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
            // Avoid the auto-generated current-month rent charge (created-event side effect
            // on active contracts) from skewing the settlement math in these tests.
            'rent_amount' => 0,
        ]);

        $depositHold = Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => $depositAmount,
        ]);

        return [$contract, $depositHold];
    }
}
