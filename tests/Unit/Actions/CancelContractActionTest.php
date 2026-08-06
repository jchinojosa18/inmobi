<?php

namespace Tests\Unit\Actions;

use App\Actions\Contracts\CancelContractAction;
use App\Actions\Contracts\RegisterDepositHoldAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\MonthClose;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CancelContractActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();
    }

    public function test_cancels_clean_contract_and_soft_deletes_open_rent(): void
    {
        $contract = $this->makeActiveContract();
        TenantContext::setOrganizationId($contract->organization_id);

        $rentIds = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_RENT)
            ->pluck('id')
            ->all();
        $this->assertNotEmpty($rentIds);

        app(CancelContractAction::class)->execute(
            contract: $contract,
            reason: 'Inquilino incorrecto',
            userId: null,
        );

        $fresh = $contract->fresh();
        $this->assertSame(Contract::STATUS_CANCELLED, $fresh->status);
        $this->assertNull($fresh->active_lock);
        $this->assertSame('Inquilino incorrecto', data_get($fresh->meta, 'cancellation_reason'));
        $this->assertNotNull(data_get($fresh->meta, 'cancelled_at'));

        foreach ($rentIds as $id) {
            $this->assertSoftDeleted('charges', ['id' => $id]);
        }

        $this->assertDatabaseHas('audit_events', [
            'action' => 'contract.cancelled',
            'auditable_type' => $contract->getMorphClass(),
            'auditable_id' => $contract->id,
        ]);

        // Unit is free for a new active contract
        $otherTenant = Tenant::factory()->create(['organization_id' => $contract->organization_id]);
        $replacement = Contract::factory()->create([
            'organization_id' => $contract->organization_id,
            'unit_id' => $contract->unit_id,
            'tenant_id' => $otherTenant->id,
            'status' => Contract::STATUS_ACTIVE,
        ]);
        $this->assertSame(Contract::STATUS_ACTIVE, $replacement->status);
        $this->assertSame(1, $replacement->active_lock);
    }

    public function test_blocks_when_payment_exists(): void
    {
        $contract = $this->makeActiveContract();
        TenantContext::setOrganizationId($contract->organization_id);

        Payment::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'amount' => 100,
        ]);

        $eligibility = app(CancelContractAction::class)->evaluate($contract);
        $this->assertFalse($eligibility->allowed);
        $this->assertTrue(collect($eligibility->blockers)->contains(fn ($b) => $b['code'] === 'has_payments'));

        $this->expectException(ValidationException::class);
        app(CancelContractAction::class)->execute($contract, 'motivo', null);
    }

    public function test_blocks_when_deposit_hold_exists(): void
    {
        $contract = $this->makeActiveContract(depositAmount: 1000.0);
        TenantContext::setOrganizationId($contract->organization_id);

        app(RegisterDepositHoldAction::class)->execute(
            contract: $contract,
            amount: 1000.0,
            receivedAt: '2026-08-01',
            notes: null,
            userId: null,
            method: Payment::METHOD_CASH,
        );

        $eligibility = app(CancelContractAction::class)->evaluate($contract);
        $this->assertFalse($eligibility->allowed);
        $this->assertTrue(collect($eligibility->blockers)->contains(fn ($b) => $b['code'] === 'has_deposit_hold'));
    }

    public function test_blocks_when_credit_balance_positive(): void
    {
        $contract = $this->makeActiveContract();
        TenantContext::setOrganizationId($contract->organization_id);

        CreditBalance::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'balance' => 50,
        ]);

        $eligibility = app(CancelContractAction::class)->evaluate($contract);
        $this->assertFalse($eligibility->allowed);
        $this->assertTrue(collect($eligibility->blockers)->contains(fn ($b) => $b['code'] === 'has_credit'));
    }

    public function test_blocks_when_charge_month_is_closed(): void
    {
        $contract = $this->makeActiveContract();
        TenantContext::setOrganizationId($contract->organization_id);

        $rent = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_RENT)
            ->firstOrFail();

        $user = User::factory()->create([
            'organization_id' => $contract->organization_id,
        ]);

        MonthClose::query()->withoutOrganizationScope()->create([
            'organization_id' => $contract->organization_id,
            'month' => $rent->period,
            'closed_at' => now(),
            'closed_by_user_id' => $user->id,
            'snapshot' => [
                'ingresos_operativos' => 0,
                'egresos' => 0,
                'neto' => 0,
                'cartera' => 0,
                'conteos' => [
                    'contratos_activos' => 0,
                    'pagos' => 0,
                    'egresos' => 0,
                ],
            ],
        ]);

        $eligibility = app(CancelContractAction::class)->evaluate($contract);
        $this->assertFalse($eligibility->allowed);
        $this->assertTrue(collect($eligibility->blockers)->contains(fn ($b) => $b['code'] === 'month_closed'));

        $this->expectException(ValidationException::class);
        app(CancelContractAction::class)->execute($contract, 'motivo', null);
    }

    public function test_blocks_when_renewed_to_another_contract(): void
    {
        $contract = $this->makeActiveContract();
        $contract->forceFill([
            'meta' => array_merge($contract->meta ?? [], ['renewed_to_contract_id' => 999]),
        ])->save();

        $eligibility = app(CancelContractAction::class)->evaluate($contract);
        $this->assertFalse($eligibility->allowed);
        $this->assertTrue(collect($eligibility->blockers)->contains(fn ($b) => $b['code'] === 'renewed'));
    }

    public function test_blocks_empty_reason(): void
    {
        $contract = $this->makeActiveContract();
        TenantContext::setOrganizationId($contract->organization_id);

        $this->expectException(ValidationException::class);
        app(CancelContractAction::class)->execute($contract, '   ', null);
    }

    public function test_blocks_ended_and_cancelled_status(): void
    {
        foreach ([Contract::STATUS_ENDED, Contract::STATUS_CANCELLED] as $status) {
            $contract = $this->makeActiveContract(status: $status);
            $eligibility = app(CancelContractAction::class)->evaluate($contract);
            $this->assertFalse($eligibility->allowed, "status {$status}");
            $this->assertTrue(collect($eligibility->blockers)->contains(fn ($b) => $b['code'] === 'wrong_status'));
        }
    }

    public function test_blocks_when_allocations_exist_without_counting_as_clean(): void
    {
        $contract = $this->makeActiveContract();
        TenantContext::setOrganizationId($contract->organization_id);

        $rent = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_RENT)
            ->firstOrFail();

        $payment = Payment::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'amount' => 10,
        ]);
        PaymentAllocation::factory()->create([
            'organization_id' => $contract->organization_id,
            'payment_id' => $payment->id,
            'charge_id' => $rent->id,
            'amount' => 10,
        ]);

        $eligibility = app(CancelContractAction::class)->evaluate($contract);
        $this->assertFalse($eligibility->allowed);
        $codes = collect($eligibility->blockers)->pluck('code');
        $this->assertTrue($codes->contains('has_payments') || $codes->contains('has_allocations'));
    }

    private function makeActiveContract(
        float $depositAmount = 0.0,
        string $status = Contract::STATUS_ACTIVE,
    ): Contract {
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
            'rent_amount' => 1000,
            'status' => $status,
            'starts_at' => '2026-08-01',
            'ends_at' => '2027-07-31',
        ]);
    }
}
