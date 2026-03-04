<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\Organization;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditBalance>
 */
class CreditBalanceFactory extends Factory
{
    protected $model = CreditBalance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'contract_id' => Contract::factory()->state(fn (array $attributes): array => [
                'organization_id' => $attributes['organization_id'],
            ]),
            'balance' => fake()->randomFloat(2, 1, 20000),
            'last_payment_id' => Payment::factory()->state(fn (array $attributes): array => [
                'organization_id' => $attributes['organization_id'],
                'contract_id' => $attributes['contract_id'],
            ]),
            'meta' => null,
        ];
    }
}
