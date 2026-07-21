<?php

namespace Tests\Feature\Houses;

use App\Livewire\Units\InventoryPanel;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StandaloneHouseInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_house_show_includes_inventory_panel(): void
    {
        [$user, $property] = $this->makeStandaloneWithUnit(
            Property::KIND_STANDALONE_HOUSE,
            Unit::KIND_HOUSE,
        );

        $this->actingAs($user)
            ->get(route('houses.show', $property))
            ->assertOk()
            ->assertSeeLivewire(InventoryPanel::class)
            ->assertSee(__('inventory.title'));
    }

    public function test_local_show_includes_inventory_panel(): void
    {
        [$user, $property] = $this->makeStandaloneWithUnit(
            Property::KIND_LOCAL,
            Unit::KIND_LOCAL,
        );

        $this->actingAs($user)
            ->get(route('houses.show', $property))
            ->assertOk()
            ->assertSeeLivewire(InventoryPanel::class)
            ->assertSee(__('inventory.title'));
    }

    public function test_land_show_does_not_include_inventory_panel(): void
    {
        [$user, $property] = $this->makeStandaloneWithUnit(
            Property::KIND_LAND,
            Unit::KIND_LAND,
        );

        $this->actingAs($user)
            ->get(route('houses.show', $property))
            ->assertOk()
            ->assertDontSeeLivewire(InventoryPanel::class)
            ->assertDontSee(__('inventory.title'));
    }

    public function test_house_show_without_units_view_loads_without_inventory_panel(): void
    {
        $organization = Organization::factory()->create();
        $role = Role::findOrCreate('SoloPropiedades', 'web');
        $role->syncPermissions(['properties.view']);
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $user->syncRoles([$role]);

        $property = Property::factory()->standaloneHouse()->create([
            'organization_id' => $organization->id,
        ]);
        Unit::factory()->house()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'name' => 'Casa',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('houses.show', $property))
            ->assertOk()
            ->assertDontSeeLivewire(InventoryPanel::class);
    }

    /**
     * @return array{0: User, 1: Property}
     */
    private function makeStandaloneWithUnit(string $propertyKind, string $unitKind): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);

        $propertyFactory = match ($propertyKind) {
            Property::KIND_STANDALONE_HOUSE => Property::factory()->standaloneHouse(),
            Property::KIND_LOCAL => Property::factory()->standaloneLocal(),
            Property::KIND_LAND => Property::factory()->standaloneLand(),
            default => throw new \InvalidArgumentException($propertyKind),
        };

        $property = $propertyFactory->create([
            'organization_id' => $organization->id,
        ]);

        $unitFactory = match ($unitKind) {
            Unit::KIND_HOUSE => Unit::factory()->house(),
            Unit::KIND_LOCAL => Unit::factory()->local(),
            Unit::KIND_LAND => Unit::factory()->land(),
            default => throw new \InvalidArgumentException($unitKind),
        };

        $unitFactory->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'name' => match ($unitKind) {
                Unit::KIND_HOUSE => 'Casa',
                Unit::KIND_LOCAL => 'Local',
                Unit::KIND_LAND => 'Terreno',
                default => 'Unit',
            },
            'status' => 'active',
        ]);

        return [$user, $property];
    }
}
