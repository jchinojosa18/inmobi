<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Organization;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

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
            'tenant_id' => Tenant::factory()->state(fn (array $attributes): array => [
                'organization_id' => $attributes['organization_id'],
            ]),
            'rent_amount' => fake()->randomFloat(2, 4500, 30000),
            'deposit_amount' => fake()->randomFloat(2, 0, 30000),
            'due_day' => fake()->numberBetween(1, 28),
            'grace_days' => 5,
            'penalty_rate_daily' => fake()->randomFloat(4, 0, 2),
            'status' => Contract::STATUS_ACTIVE,
            'active_lock' => 1,
            'starts_at' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'ends_at' => null,
            'meta' => null,
        ];
    }

    public function ended(): static
    {
        return $this->state(fn (): array => [
            'status' => Contract::STATUS_ENDED,
            'active_lock' => null,
            'ends_at' => fake()->dateTimeBetween('now', '+12 months')->format('Y-m-d'),
        ]);
    }
}
