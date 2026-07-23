<?php

namespace Tests\Feature\Contracts;

use App\Livewire\Contracts\SettlementWizard;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettlementWizardDepositRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_updates_when_deposit_hold_registered_event_fires(): void
    {
        [$user, $contract] = $this->makeContractWithUser(depositAmount: 10000.0);

        $component = Livewire::actingAs($user)
            ->test(SettlementWizard::class, ['contract' => $contract])
            ->assertSee(__('contracts.deposit_paid'))
            ->assertSee('$0.00');

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-07',
            'charge_date' => '2026-07-21',
            'amount' => 10000,
        ]);

        // Without listeners, dispatch alone may not refresh; after Task 2 it must.
        $component
            ->dispatch('deposit-hold-registered')
            ->assertSee(__('contracts.deposit_paid'))
            ->assertSee('$10,000.00')
            ->assertSee(__('contracts.available'))
            ->assertSee(__('contracts.deposit_applied'))
            ->assertSee(__('contracts.deposit_refunded'))
            ->assertSee(__('contracts.current_outstanding'));
    }

    public function test_summary_updates_when_deposit_hold_voided_event_fires(): void
    {
        [$user, $contract] = $this->makeContractWithUser(depositAmount: 10000.0);

        $hold = Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-07',
            'charge_date' => '2026-07-21',
            'amount' => 10000,
        ]);

        $component = Livewire::actingAs($user)
            ->test(SettlementWizard::class, ['contract' => $contract])
            ->assertSee('$10,000.00');

        $hold->delete(); // soft-delete; registered sum excludes it

        $component
            ->dispatch('deposit-hold-voided')
            ->assertSee(__('contracts.deposit_paid'))
            ->assertSee('$0.00');
    }

    /**
     * @return array{0: User, 1: Contract}
     */
    private function makeContractWithUser(float $depositAmount): array
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
            'deposit_amount' => $depositAmount,
            'rent_amount' => 0,
            'status' => Contract::STATUS_ACTIVE,
        ]);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($user);

        return [$user, $contract];
    }
}
