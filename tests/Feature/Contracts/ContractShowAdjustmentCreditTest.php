<?php

namespace Tests\Feature\Contracts;

use App\Livewire\Contracts\Show;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContractShowAdjustmentCreditTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_creating_positive_adjustment_consumes_credit_balance(): void
    {
        [$user, $contract] = $this->makeContractWithCredit(credit: 400.0);

        Livewire::actingAs($user)
            ->test(Show::class, ['contract' => $contract])
            ->set('adjustment_amount', '250')
            ->set('adjustment_charge_date', '2026-07-15')
            ->set('adjustment_reason', 'Corrección de saldo')
            ->call('createAdjustment')
            ->assertHasNoErrors();

        $adjustment = Charge::query()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_ADJUSTMENT)
            ->first();

        $this->assertNotNull($adjustment);
        $this->assertSame(250.0, (float) $adjustment->amount);

        $this->assertSame(
            150.0,
            (float) CreditBalance::query()->where('contract_id', $contract->id)->value('balance')
        );

        $creditPayment = Payment::query()
            ->where('contract_id', $contract->id)
            ->where('method', Payment::METHOD_CREDIT)
            ->first();

        $this->assertNotNull($creditPayment);
        $this->assertSame(250.0, (float) $creditPayment->amount);
        $this->assertNull($creditPayment->receipt_folio);

        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $creditPayment->id,
            'charge_id' => $adjustment->id,
            'amount' => '250.00',
        ]);
    }

    public function test_creating_negative_adjustment_does_not_consume_credit(): void
    {
        [$user, $contract] = $this->makeContractWithCredit(credit: 400.0);

        Livewire::actingAs($user)
            ->test(Show::class, ['contract' => $contract])
            ->set('adjustment_amount', '-100')
            ->set('adjustment_charge_date', '2026-07-15')
            ->set('adjustment_reason', 'Descuento autorizado')
            ->call('createAdjustment')
            ->assertHasNoErrors();

        $this->assertSame(
            400.0,
            (float) CreditBalance::query()->where('contract_id', $contract->id)->value('balance')
        );
        $this->assertSame(0, Payment::query()->where('contract_id', $contract->id)->count());
        $this->assertSame(0, PaymentAllocation::query()->count());
    }

    /**
     * @return array{0: User, 1: Contract}
     */
    private function makeContractWithCredit(float $credit): array
    {
        $organization = Organization::factory()->create();
        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $tenant = Tenant::factory()->create(['organization_id' => $organization->id]);
        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            // Avoid auto-RENT on create so credit only targets the adjustment.
            'status' => Contract::STATUS_ENDED,
            'ends_at' => '2026-12-31',
            'rent_amount' => 0,
        ]);

        CreditBalance::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'balance' => $credit,
        ]);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($user);
        TenantContext::setOrganizationId($organization->id);

        return [$user, $contract];
    }
}
