<?php

namespace Tests\Feature\Units;

use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitInventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitInventoryModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_has_inventory_items_relation(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $item = UnitInventoryItem::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'name' => 'Refrigerador',
            'quantity' => 1,
            'condition' => UnitInventoryItem::CONDITION_GOOD,
        ]);

        $this->actingAs($user);

        $this->assertTrue($unit->fresh()->inventoryItems->contains($item));
    }
}
