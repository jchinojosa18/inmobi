<?php

namespace Tests\Feature\Contracts;

use App\Livewire\Contracts\CreateModal;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContractCreateModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_modal_loads_contract_data(): void
    {
        [$organization, $contract, $user] = $this->createContractGraph();

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->dispatch('open-contract-edit', contractId: $contract->id)
            ->assertSet('open', true)
            ->assertSet('contractId', $contract->id)
            ->assertSet('unit_id', $contract->unit_id)
            ->assertSet('tenant_id', $contract->tenant_id)
            ->assertSet('rent_amount', (string) $contract->rent_amount)
            ->assertSet('penalty_rate_daily', '5.0000');
    }

    public function test_edit_modal_updates_contract_and_dispatches_event(): void
    {
        [$organization, $contract, $user] = $this->createContractGraph();

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->dispatch('open-contract-edit', contractId: $contract->id)
            ->set('rent_amount', '12500')
            ->set('meta_notes', 'Nota actualizada')
            ->call('save')
            ->assertSet('open', false)
            ->assertDispatched('contract-updated');

        $contract->refresh();

        $this->assertSame('12500.00', (string) $contract->rent_amount);
        $this->assertSame('Nota actualizada', data_get($contract->meta, 'notes'));
    }

    public function test_component_is_mounted_in_layout_on_contract_show(): void
    {
        [$organization, $contract, $user] = $this->createContractGraph();

        $this->actingAs($user)
            ->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSeeLivewire(CreateModal::class);
    }

    /**
     * @return array{Organization, Contract, User}
     */
    private function createContractGraph(): array
    {
        $organization = Organization::factory()->create();

        $property = Property::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'status' => 'active',
        ]);

        $tenant = Tenant::factory()->create([
            'organization_id' => $organization->id,
            'status' => 'active',
        ]);

        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'rent_amount' => 10000,
            'penalty_rate_daily' => 0.05,
        ]);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        return [$organization, $contract, $user];
    }
}
