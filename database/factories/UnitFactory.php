<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'property_id' => Property::factory()->state(fn (array $attributes): array => [
                'organization_id' => $attributes['organization_id'],
            ]),
            'name' => 'Unit '.strtoupper(fake()->bothify('##?')),
            'code' => strtoupper(fake()->unique()->bothify('U-###')),
            'status' => 'active',
            'kind' => Unit::KIND_APARTMENT,
            'floor' => (string) fake()->numberBetween(1, 20),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function house(): static
    {
        return $this->state(fn (): array => [
            'kind' => Unit::KIND_HOUSE,
            'name' => 'Casa',
            'floor' => null,
        ]);
    }
}
