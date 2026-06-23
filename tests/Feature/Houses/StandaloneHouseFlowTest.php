<?php

namespace Tests\Feature\Houses;

use App\Livewire\Contracts\CreateModal as ContractCreateModal;
use App\Livewire\Properties\CreateModal as PropertyCreateModal;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StandaloneHouseFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_house_in_one_step_with_property_and_single_unit(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        Livewire::actingAs($user)
            ->test(PropertyCreateModal::class)
            ->call('open')
            ->call('selectType', PropertyCreateModal::TYPE_HOUSE)
            ->set('name', 'Casa Calle X #123')
            ->set('address', 'Calle X #123, Centro')
            ->set('notes', 'Casa de prueba')
            ->call('save')
            ->assertSet('open', false)
            ->assertDispatched('property-created');

        $property = Property::query()->where('name', 'CASA CALLE X #123')->first();

        $this->assertNotNull($property);
        $this->assertSame(Property::KIND_STANDALONE_HOUSE, $property->kind);
        $this->assertSame('CALLE X #123, CENTRO', $property->address);

        $houseUnits = Unit::query()
            ->where('property_id', $property->id)
            ->get();

        $this->assertCount(1, $houseUnits);
        $this->assertSame(Unit::KIND_HOUSE, $houseUnits->first()->kind);
        $this->assertSame('Casa', $houseUnits->first()->name);
    }

    public function test_units_route_redirects_to_house_detail_for_standalone_house(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $property = Property::factory()->standaloneHouse()->create([
            'organization_id' => $organization->id,
            'name' => 'Casa de redireccion',
        ]);

        Unit::factory()->house()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'name' => 'Casa',
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('properties.units.index', $property));

        $response->assertRedirect(route('houses.show', $property));
    }

    public function test_house_unit_is_available_in_contract_create_selector(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $property = Property::factory()->standaloneHouse()->create([
            'organization_id' => $organization->id,
            'name' => 'Casa Mision San Diego',
        ]);

        Unit::factory()->house()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'name' => 'Casa',
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $unit = Unit::query()->where('property_id', $property->id)->firstOrFail();

        Livewire::actingAs($user)
            ->test(ContractCreateModal::class)
            ->call('open')
            ->assertSee('CASA MISION SAN DIEGO — Casa');

        Livewire::actingAs($user)
            ->test(ContractCreateModal::class)
            ->call('open', $unit->id)
            ->assertSet('unit_id', $unit->id);
    }
}
