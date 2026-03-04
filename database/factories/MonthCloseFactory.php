<?php

namespace Database\Factories;

use App\Models\MonthClose;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonthClose>
 */
class MonthCloseFactory extends Factory
{
    protected $model = MonthClose::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'month' => fake()->date('Y-m'),
            'closed_at' => fake()->dateTimeBetween('-6 months', 'now'),
            'closed_by_user_id' => User::factory()->state(fn (array $attributes): array => [
                'organization_id' => $attributes['organization_id'],
            ]),
            'snapshot' => [
                'charges_total' => fake()->randomFloat(2, 10_000, 200_000),
                'payments_total' => fake()->randomFloat(2, 10_000, 200_000),
                'expenses_total' => fake()->randomFloat(2, 1_000, 100_000),
            ],
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
