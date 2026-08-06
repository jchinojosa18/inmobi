<?php

namespace Tests\Unit\Actions;

use App\Actions\Contracts\RegisterDepositHoldAction;
use App\Actions\Contracts\VoidDepositHoldAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\DepositBalanceService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VoidDepositHoldActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();
    }

    public function test_void_soft_deletes_hold_and_restores_remaining(): void
    {
        $contract = $this->makeContract(1000.0);
        TenantContext::setOrganizationId($contract->organization_id);

        $hold = app(RegisterDepositHoldAction::class)->execute(
            contract: $contract,
            amount: 1000.0,
            receivedAt: '2026-07-21',
            notes: null,
            userId: null,
            method: Payment::METHOD_CASH,
        );

        $this->assertNotEmpty(data_get($hold->meta, 'deposit_receipt_folio'));

        app(VoidDepositHoldAction::class)->execute($contract, $hold->id, null);

        $this->assertSoftDeleted('charges', ['id' => $hold->id]);
        $this->assertSame(0.0, app(DepositBalanceService::class)->registeredDepositHoldAmount($contract));
        $this->assertSame(1000.0, app(DepositBalanceService::class)->remainingDepositHoldAmount($contract));
        $this->assertSame(0.0, app(DepositBalanceService::class)->availableDepositAmount($contract));
    }

    public function test_void_clears_orphan_payment_allocated_only_to_deposit_hold(): void
    {
        $contract = $this->makeContract(1000.0);
        TenantContext::setOrganizationId($contract->organization_id);

        $hold = Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-07',
            'charge_date' => '2026-07-21',
            'amount' => 1000,
            'meta' => [
                'deposit_receipt_folio' => 'DEP-2026-00001',
            ],
        ]);

        $payment = Payment::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'amount' => 1000,
            'meta' => [],
        ]);

        PaymentAllocation::factory()->create([
            'organization_id' => $contract->organization_id,
            'payment_id' => $payment->id,
            'charge_id' => $hold->id,
            'amount' => 1000,
        ]);

        app(VoidDepositHoldAction::class)->execute($contract, $hold->id, null);

        $this->assertSoftDeleted('charges', ['id' => $hold->id]);
        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
    }

    public function test_void_keeps_payment_if_it_still_has_other_allocations(): void
    {
        $contract = $this->makeContract(1000.0);
        TenantContext::setOrganizationId($contract->organization_id);

        $hold = Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-07',
            'charge_date' => '2026-07-21',
            'amount' => 500,
            'meta' => [
                'deposit_receipt_folio' => 'DEP-2026-00002',
            ],
        ]);

        $rent = Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_RENT,
            'period' => '2026-06',
            'charge_date' => '2026-06-01',
            'amount' => 500,
        ]);

        $payment = Payment::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'amount' => 1000,
            'meta' => [],
        ]);

        PaymentAllocation::factory()->create([
            'organization_id' => $contract->organization_id,
            'payment_id' => $payment->id,
            'charge_id' => $hold->id,
            'amount' => 500,
        ]);

        PaymentAllocation::factory()->create([
            'organization_id' => $contract->organization_id,
            'payment_id' => $payment->id,
            'charge_id' => $rent->id,
            'amount' => 500,
        ]);

        app(VoidDepositHoldAction::class)->execute($contract, $hold->id, null);

        $this->assertSoftDeleted('charges', ['id' => $hold->id]);
        $this->assertNull($payment->fresh()->deleted_at);
        $this->assertSame(1, PaymentAllocation::query()->where('payment_id', $payment->id)->count());
    }

    public function test_void_is_blocked_when_contract_is_ended(): void
    {
        $contract = $this->makeContract(1000.0, Contract::STATUS_ENDED);
        TenantContext::setOrganizationId($contract->organization_id);

        $hold = Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-07',
            'charge_date' => '2026-07-21',
            'amount' => 1000,
        ]);

        $this->expectException(ValidationException::class);
        app(VoidDepositHoldAction::class)->execute($contract, $hold->id, null);
    }

    private function makeContract(float $depositAmount, string $status = Contract::STATUS_ACTIVE): Contract
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
            'status' => $status,
        ]);
    }
}
