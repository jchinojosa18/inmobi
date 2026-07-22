<?php

namespace Tests\Feature\Contracts;

use App\Livewire\Contracts\DepositHoldForm;
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

class DepositHoldFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_prefills_remaining_and_allows_partial_registration(): void
    {
        [$user, $contract] = $this->makeContractWithUser(depositAmount: 1000.0);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 400,
        ]);

        Livewire::actingAs($user)
            ->test(DepositHoldForm::class, ['contract' => $contract])
            ->assertSet('deposit_amount', '600.00')
            ->assertSee(__('contracts.deposit_remaining'))
            ->set('deposit_received_at', '2026-03-10')
            ->set('deposit_amount', '600.00')
            ->call('registerDeposit')
            ->assertHasNoErrors();

        $this->assertSame(1000.0, (float) Charge::query()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_DEPOSIT_HOLD)
            ->sum('amount'));
    }

    public function test_form_is_hidden_when_deposit_is_complete(): void
    {
        [$user, $contract] = $this->makeContractWithUser(depositAmount: 500.0);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 500,
        ]);

        Livewire::actingAs($user)
            ->test(DepositHoldForm::class, ['contract' => $contract])
            ->assertSee(__('contracts.deposit_complete_title'))
            ->assertDontSee(__('contracts.register_deposit'));
    }

    public function test_registering_above_remaining_shows_error(): void
    {
        [$user, $contract] = $this->makeContractWithUser(depositAmount: 1000.0);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 800,
        ]);

        Livewire::actingAs($user)
            ->test(DepositHoldForm::class, ['contract' => $contract])
            ->set('deposit_received_at', '2026-03-10')
            ->set('deposit_amount', '300.00')
            ->call('registerDeposit')
            ->assertHasErrors(['deposit_general']);
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
        ]);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        // Ensure Admin (charges.manage) even when using Livewire::actingAs
        $this->actingAs($user);

        return [$user, $contract];
    }
}
