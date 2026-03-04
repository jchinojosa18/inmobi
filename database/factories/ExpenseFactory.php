<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\Organization;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

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
            'category' => fake()->randomElement(['maintenance', 'services', 'supplies', 'other']),
            'amount' => fake()->randomFloat(2, 100, 20000),
            'spent_at' => fake()->date(),
            'vendor' => fake()->optional()->company(),
            'notes' => fake()->optional()->sentence(),
            'meta' => null,
        ];
    }

    public function general(): static
    {
        return $this->state(fn (): array => [
            'unit_id' => null,
        ]);
    }
}
