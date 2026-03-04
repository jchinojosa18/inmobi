<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->streetName().' '.fake()->buildingNumber(),
            'code' => strtoupper(fake()->unique()->bothify('PROP-###??')),
            'status' => 'active',
            'kind' => Property::KIND_BUILDING,
            'address' => fake()->address(),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function standaloneHouse(): static
    {
        return $this->state(fn (): array => [
            'kind' => Property::KIND_STANDALONE_HOUSE,
        ]);
    }
}
