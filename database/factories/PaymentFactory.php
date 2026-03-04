<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Organization;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

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
            'paid_at' => fake()->dateTimeBetween('-2 months', 'now'),
            'amount' => fake()->randomFloat(2, 200, 30000),
            'method' => fake()->randomElement([
                Payment::METHOD_CASH,
                Payment::METHOD_TRANSFER,
            ]),
            'reference' => fake()->optional()->bothify('REF-#######'),
            'receipt_folio' => strtoupper(fake()->unique()->bothify('REC-######')),
            'meta' => null,
        ];
    }
}
