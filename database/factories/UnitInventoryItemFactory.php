<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Unit;
use App\Models\UnitInventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitInventoryItem>
 */
class UnitInventoryItemFactory extends Factory
{
    protected $model = UnitInventoryItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'unit_id' => Unit::factory()->state(fn (array $attributes): array => [
                'organization_id' => $attributes['organization_id'],
            ]),
            'name' => fake()->words(2, true),
            'quantity' => 1,
            'condition' => UnitInventoryItem::CONDITION_GOOD,
            'notes' => null,
            'sort_order' => 0,
        ];
    }
}
