<?php

namespace Tests\Unit\Actions;

use App\Actions\Contracts\ProcessContractSettlementAction;
use App\Actions\Expenses\SeedDefaultExpenseCategoriesAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\ExpenseCategory;
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

        $this->assertDatabaseHas('payments', [
            'contract_id' => $contract->id,
            'method' => Payment::METHOD_DEPOSIT,
            'amount' => '500.00',
            'receipt_folio' => null,
        ]);

        $moveout = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_MOVEOUT)
            ->firstOrFail();

        $this->assertSame(
            200.0,
            round((float) PaymentAllocation::query()
                ->withoutOrganizationScope()
                ->where('charge_id', $moveout->id)
                ->sum('amount'), 2)
        );

        $refundCategoryId = ExpenseCategory::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $contract->organization_id)
            ->where('name', 'REEMBOLSO DEPÓSITO')
            ->value('id');

        $this->assertDatabaseHas('expenses', [
            'organization_id' => $contract->organization_id,
            'expense_category_id' => $refundCategoryId,
            'contract_id' => $contract->id,
            'amount' => 500,
        ]);

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'status' => Contract::STATUS_ENDED,
            'ends_at' => '2026-03-20 00:00:00',
        ]);
    }

    public function test_deposit_payment_clears_moveout_balance_when_rent_already_paid(): void
    {
        [$contract, $depositHold] = $this->createContractWithDepositHold(1000);
        TenantContext::setOrganizationId((int) $contract->organization_id);

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

        $rent = Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_RENT,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 1000,
        ]);

        $rentPayment = Payment::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'paid_at' => '2026-03-05 10:00:00',
            'amount' => 1000,
            'method' => Payment::METHOD_CASH,
        ]);

        PaymentAllocation::factory()->create([
            'organization_id' => $contract->organization_id,
            'payment_id' => $rentPayment->id,
            'charge_id' => $rent->id,
            'amount' => 1000,
        ]);

        $result = app(ProcessContractSettlementAction::class)->execute(
            contract: $contract->fresh(),
            moveOutDate: '2026-03-20',
            concepts: [
                ['description' => 'Fin', 'amount' => 1000],
            ],
            userId: null,
        );

        $this->assertSame(1000.0, $result->depositApplied);
        $this->assertSame(0.0, $result->depositRefund);
        $this->assertSame(0.0, $result->balanceToCollect);

        $moveout = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_MOVEOUT)
            ->firstOrFail();

        $allocatedToMoveout = round((float) PaymentAllocation::query()
            ->withoutOrganizationScope()
            ->where('charge_id', $moveout->id)
            ->sum('amount'), 2);

        $this->assertSame(1000.0, $allocatedToMoveout);
        $this->assertSame(0.0, round((float) $moveout->amount - $allocatedToMoveout, 2));

        $depositLedgerPayment = Payment::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->where('method', Payment::METHOD_DEPOSIT)
            ->firstOrFail();

        $this->assertSame('1000.00', (string) $depositLedgerPayment->amount);
        $this->assertSame(
            (string) data_get($contract->fresh()->meta, 'settlement_batch_id'),
            (string) data_get($depositLedgerPayment->meta, 'settlement_batch_id')
        );
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

        $refundCategoryId = ExpenseCategory::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $contract->organization_id)
            ->where('name', 'REEMBOLSO DEPÓSITO')
            ->value('id');

        $this->assertDatabaseMissing('expenses', [
            'organization_id' => $contract->organization_id,
            'expense_category_id' => $refundCategoryId,
            'contract_id' => $contract->id,
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

    public function test_leftover_credit_is_refunded_with_deposit_at_settlement(): void
    {
        [$contract] = $this->createContractWithDepositHold(1000);

        CreditBalance::query()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'balance' => 250,
        ]);

        $result = app(ProcessContractSettlementAction::class)->execute(
            contract: $contract,
            moveOutDate: '2026-03-20',
            concepts: [],
            userId: null,
        );

        $this->assertSame(0.0, $result->outstandingBeforeDeposit);
        $this->assertSame(0.0, $result->depositApplied);
        $this->assertSame(1250.0, $result->depositRefund);
        $this->assertSame(0.0, $result->balanceToCollect);

        $this->assertSame(
            0.0,
            (float) CreditBalance::query()->withoutOrganizationScope()->where('contract_id', $contract->id)->value('balance')
        );

        $refundCategoryId = ExpenseCategory::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $contract->organization_id)
            ->where('name', 'REEMBOLSO DEPÓSITO')
            ->value('id');

        $this->assertDatabaseHas('expenses', [
            'organization_id' => $contract->organization_id,
            'expense_category_id' => $refundCategoryId,
            'contract_id' => $contract->id,
            'amount' => 1250,
        ]);
    }

    public function test_settled_negative_adjustment_does_not_double_count_in_settlement_outstanding(): void
    {
        [$contract, $depositHold] = $this->createContractWithDepositHold(1000);
        TenantContext::setOrganizationId((int) $contract->organization_id);

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
            'amount' => 1000,
        ]);

        $user = \App\Models\User::factory()->create([
            'organization_id' => $contract->organization_id,
        ]);

        CreditBalance::query()->updateOrCreate(
            [
                'organization_id' => $contract->organization_id,
                'contract_id' => $contract->id,
            ],
            ['balance' => 0]
        );

        app(\App\Actions\Charges\RegisterContractAdjustmentAction::class)->execute(
            contract: $contract,
            amount: -200.0,
            chargeDate: \Carbon\CarbonImmutable::parse('2026-03-10'),
            reason: 'Condonación parcial',
            createdByUserId: (int) $user->id,
        );

        $result = app(ProcessContractSettlementAction::class)->execute(
            contract: $contract->fresh(),
            moveOutDate: '2026-03-20',
            concepts: [],
            userId: (int) $user->id,
        );

        // RENT 1000 − crédito 200 = 800. No debe ser 600 (doble descuento).
        $this->assertSame(800.0, $result->outstandingBeforeDeposit);
        $this->assertSame(800.0, $result->depositApplied);
        $this->assertSame(200.0, $result->depositRefund);
        $this->assertSame(0.0, $result->balanceToCollect);
    }

    /**
     * @return array{0: Contract, 1: Charge}
     */
    private function createContractWithDepositHold(float $depositAmount): array
    {
        $organization = Organization::factory()->create();
        app(SeedDefaultExpenseCategoriesAction::class)->execute((int) $organization->id);

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
