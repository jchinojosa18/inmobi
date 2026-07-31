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

    public function test_creating_negative_adjustment_increases_credit_when_no_pending(): void
    {
        [$user, $contract] = $this->makeContractWithCredit(credit: 400.0);

        Livewire::actingAs($user)
            ->test(Show::class, ['contract' => $contract])
            ->set('adjustment_amount', '-100')
            ->set('adjustment_charge_date', '2026-07-15')
            ->set('adjustment_reason', 'Descuento autorizado')
            ->call('createAdjustment')
            ->assertHasNoErrors();

        $adjustment = Charge::query()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_ADJUSTMENT)
            ->first();

        $this->assertNotNull($adjustment);
        $this->assertSame(-100.0, (float) $adjustment->amount);
        $this->assertTrue((bool) data_get($adjustment->meta, 'settled_as_credit'));
        $this->assertSame(100.0, (float) data_get($adjustment->meta, 'credit_amount'));

        $this->assertSame(
            500.0,
            (float) CreditBalance::query()->where('contract_id', $contract->id)->value('balance')
        );

        // No pending charges → ApplyCreditBalance is a no-op (no CREDIT payment required).
        $this->assertSame(
            0,
            Payment::query()->where('contract_id', $contract->id)->where('method', Payment::METHOD_CREDIT)->count()
        );
    }

    public function test_creating_negative_adjustment_applies_credit_to_pending_rent(): void
    {
        [$user, $contract] = $this->makeContractWithCredit(credit: 0.0, withUnpaidRent: 1000.0);

        Livewire::actingAs($user)
            ->test(Show::class, ['contract' => $contract])
            ->set('adjustment_amount', '-200')
            ->set('adjustment_charge_date', '2026-07-15')
            ->set('adjustment_reason', 'Condonación parcial')
            ->call('createAdjustment')
            ->assertHasNoErrors();

        $rent = Charge::query()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_RENT)
            ->first();

        $this->assertNotNull($rent);
        $allocated = (float) PaymentAllocation::query()->where('charge_id', $rent->id)->sum('amount');
        $this->assertSame(200.0, $allocated);

        $this->assertSame(
            0.0,
            (float) CreditBalance::query()->where('contract_id', $contract->id)->value('balance')
        );
    }

    /**
     * @return array{0: User, 1: Contract}
     */
    private function makeContractWithCredit(float $credit, float $withUnpaidRent = 0.0): array
    {
        $organization = Organization::factory()->create();
        TenantContext::setOrganizationId($organization->id);
        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $tenant = Tenant::factory()->create(['organization_id' => $organization->id]);

        $contractAttrs = [
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'rent_amount' => $withUnpaidRent > 0 ? $withUnpaidRent : 0,
        ];

        if ($withUnpaidRent <= 0) {
            $contractAttrs['status'] = Contract::STATUS_ENDED;
            $contractAttrs['ends_at'] = '2026-12-31';
        }

        $contract = Contract::factory()->create($contractAttrs);

        if ($withUnpaidRent > 0) {
            // Ensure a single unpaid RENT exists for the test month (factory/create hooks may already add one).
            $rent = Charge::query()
                ->where('contract_id', $contract->id)
                ->where('type', Charge::TYPE_RENT)
                ->first();

            if ($rent === null) {
                Charge::query()->create([
                    'organization_id' => $organization->id,
                    'contract_id' => $contract->id,
                    'unit_id' => $unit->id,
                    'type' => Charge::TYPE_RENT,
                    'period' => '2026-07',
                    'rent_period_key' => '2026-07',
                    'charge_date' => '2026-07-01',
                    'due_date' => '2026-07-15',
                    'amount' => $withUnpaidRent,
                    'meta' => [],
                ]);
            } else {
                $rent->update(['amount' => $withUnpaidRent]);
            }
        }

        if ($credit > 0 || CreditBalance::query()->where('contract_id', $contract->id)->exists()) {
            CreditBalance::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'contract_id' => $contract->id,
                ],
                ['balance' => $credit]
            );
        } else {
            CreditBalance::query()->create([
                'organization_id' => $organization->id,
                'contract_id' => $contract->id,
                'balance' => 0,
            ]);
        }

        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($user);
        TenantContext::setOrganizationId($organization->id);

        return [$user, $contract];
    }
}
