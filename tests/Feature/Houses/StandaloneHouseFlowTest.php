<?php

namespace Tests\Feature\Houses;

use App\Livewire\Houses\Create as HouseCreate;
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
            ->test(HouseCreate::class)
            ->set('name', 'Casa Calle X #123')
            ->set('address', 'Calle X #123, Centro')
            ->set('notes', 'Casa de prueba')
            ->call('save')
            ->assertRedirect(route('properties.index'));

        $property = Property::query()->where('name', 'Casa Calle X #123')->first();

        $this->assertNotNull($property);
        $this->assertSame(Property::KIND_STANDALONE_HOUSE, $property->kind);

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

        $response = $this
            ->actingAs($user)
            ->get(route('contracts.create'));

        $response->assertOk();
        $response->assertSeeText('Casa Mision San Diego — Casa');
    }
}
