<?php

namespace Tests\Feature\Contracts;

use App\Livewire\Contracts\Show;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContractShowDepositPendingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_balance_excludes_unpaid_deposit_hold(): void
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
            'status' => Contract::STATUS_ENDED,
            'rent_amount' => 0,
            'deposit_amount' => 10000,
            'ends_at' => '2026-12-31',
        ]);

        $rent = Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_RENT,
            'period' => '2026-07',
            'charge_date' => '2026-07-01',
            'amount' => 10000,
        ]);

        Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-07',
            'charge_date' => '2026-07-21',
            'amount' => 10000,
        ]);

        $payment = Payment::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'amount' => 10000,
            'paid_at' => '2026-07-02 12:00:00',
        ]);

        PaymentAllocation::factory()->create([
            'organization_id' => $organization->id,
            'payment_id' => $payment->id,
            'charge_id' => $rent->id,
            'amount' => 10000,
        ]);

        $user = User::factory()->create(['organization_id' => $organization->id]);

        $response = $this->actingAs($user)->get(route('contracts.show', $contract));

        $response->assertOk();
        $response->assertSeeText('Garantía');
        $response->assertSeeText('Saldo pendiente');
        // Operational pending is $0; deposit must not inflate the header box.
        $response->assertSeeText('$0.00');
        $response->assertDontSeeText('Pendiente', false);
    }

    public function test_deposit_hold_row_shows_zero_balance_and_paid_equals_amount(): void
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
            'status' => Contract::STATUS_ACTIVE,
            'deposit_amount' => 10000,
        ]);

        Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-07',
            'charge_date' => '2026-07-21',
            'amount' => 10000,
            'meta' => [
                'subtype' => 'RECEIVED',
                'deposit_receipt_folio' => 'DEP-2026-00001',
            ],
        ]);

        $user = User::factory()->create(['organization_id' => $organization->id]);

        $component = Livewire::actingAs($user)
            ->test(Show::class, ['contract' => $contract]);

        $depositRow = collect($component->viewData('ledgerGroups'))
            ->flatMap(fn (array $group) => $group['rows'])
            ->firstWhere('type', Charge::TYPE_DEPOSIT_HOLD);

        $this->assertNotNull($depositRow);
        $this->assertSame(10000.0, $depositRow['amount']);
        $this->assertSame(10000.0, $depositRow['paid']);
        $this->assertSame(0.0, $depositRow['balance']);
        $this->assertSame(__('contracts.charge_statuses.guarantee'), $depositRow['status_label']);
    }
}
