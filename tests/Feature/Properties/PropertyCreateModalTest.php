<?php

namespace Tests\Feature\Properties;

use App\Livewire\Properties\CreateModal;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PropertyCreateModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_house_with_property_and_single_unit(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->call('open')
            ->call('selectType', CreateModal::TYPE_HOUSE)
            ->set('name', 'Casa Calle X')
            ->call('save')
            ->assertSet('open', false)
            ->assertDispatched('property-created');

        $property = Property::query()->where('name', 'CASA CALLE X')->first();

        $this->assertNotNull($property);
        $this->assertSame(Property::KIND_STANDALONE_HOUSE, $property->kind);
        $this->assertCount(1, $property->units);
        $this->assertSame(Unit::KIND_HOUSE, $property->units->first()->kind);
    }

    public function test_it_creates_local_with_property_and_single_unit(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->call('open')
            ->call('selectType', CreateModal::TYPE_LOCAL)
            ->set('name', 'Local Centro')
            ->call('save')
            ->assertHasNoErrors();

        $property = Property::query()->where('name', 'LOCAL CENTRO')->first();

        $this->assertNotNull($property);
        $this->assertSame(Property::KIND_LOCAL, $property->kind);
        $this->assertSame(Unit::KIND_LOCAL, $property->units->first()->kind);
        $this->assertSame('Local', $property->units->first()->name);
    }

    public function test_it_creates_land_with_property_and_single_unit(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->call('open')
            ->call('selectType', CreateModal::TYPE_LAND)
            ->set('name', 'Terreno Norte')
            ->call('save')
            ->assertHasNoErrors();

        $property = Property::query()->where('name', 'TERRENO NORTE')->first();

        $this->assertNotNull($property);
        $this->assertSame(Property::KIND_LAND, $property->kind);
        $this->assertSame(Unit::KIND_LAND, $property->units->first()->kind);
    }

    public function test_it_redirects_to_units_bulk_wizard_after_building_create(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->call('open')
            ->call('selectType', CreateModal::TYPE_BUILDING)
            ->set('name', 'Edificio Central')
            ->set('code', 'EDIF-C')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('properties.units.index', [
                'property' => Property::query()->where('code', 'EDIF-C')->value('id'),
                'bulk' => 1,
            ]));

        $property = Property::query()->where('code', 'EDIF-C')->first();

        $this->assertNotNull($property);
        $this->assertSame(Property::KIND_BUILDING, $property->kind);
        $this->assertCount(0, $property->units);
    }

    public function test_it_stores_name_code_and_address_in_uppercase(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->call('open')
            ->call('selectType', CreateModal::TYPE_BUILDING)
            ->set('name', 'edificio norte')
            ->set('code', 'edif-n')
            ->set('address', 'calle 5 #10')
            ->call('save')
            ->assertHasNoErrors();

        $property = Property::query()->where('code', 'EDIF-N')->first();

        $this->assertNotNull($property);
        $this->assertSame('EDIFICIO NORTE', $property->name);
        $this->assertSame('EDIF-N', $property->code);
        $this->assertSame('CALLE 5 #10', $property->address);
    }
}
