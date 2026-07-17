<?php

namespace Tests\Feature\Units;

use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UnitInventoryShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_page_requires_units_view_permission(): void
    {
        $organization = Organization::factory()->create();
        $role = Role::findOrCreate('SinUnidades', 'web');
        $role->syncPermissions(['dashboard.view']);
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $user->syncRoles([$role]);

        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'kind' => Property::KIND_BUILDING,
        ]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);

        $this->actingAs($user)
            ->get(route('properties.units.show', ['property' => $property, 'unit' => $unit]))
            ->assertForbidden();
    }

    public function test_show_page_renders_for_building_unit(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'kind' => Property::KIND_BUILDING,
        ]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'code' => '101',
        ]);

        $this->actingAs($user)
            ->get(route('properties.units.show', ['property' => $property, 'unit' => $unit]))
            ->assertOk()
            ->assertSee('101')
            ->assertSee(__('inventory.title'));
    }

    public function test_show_page_blocks_foreign_organization_unit(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'kind' => Property::KIND_BUILDING,
        ]);
        $foreignUnit = Unit::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $this->actingAs($user)
            ->get(route('properties.units.show', ['property' => $property, 'unit' => $foreignUnit]))
            ->assertNotFound();
    }
}
