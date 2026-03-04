<?php

namespace Database\Factories;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Charge>
 */
class ChargeFactory extends Factory
{
    protected $model = Charge::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $chargeDate = CarbonImmutable::instance(fake()->dateTimeBetween('-6 months', 'now'))->startOfDay();
        $dueDate = $chargeDate->addDays(fake()->numberBetween(0, 10));
        $graceUntil = $dueDate->addDays(fake()->numberBetween(0, 7));
        $type = fake()->randomElement([
            Charge::TYPE_RENT,
            Charge::TYPE_PENALTY,
            Charge::TYPE_SERVICE,
            Charge::TYPE_DAMAGE,
            Charge::TYPE_CLEANING,
            Charge::TYPE_ADJUSTMENT,
            Charge::TYPE_OTHER,
            Charge::TYPE_DEPOSIT_HOLD,
            Charge::TYPE_MOVEOUT,
            Charge::TYPE_DEPOSIT_APPLY,
        ]);

        return [
            'organization_id' => Organization::factory(),
            'contract_id' => Contract::factory()->state(fn (array $attributes): array => [
                'organization_id' => $attributes['organization_id'],
            ]),
            'unit_id' => Unit::factory()->state(fn (array $attributes): array => [
                'organization_id' => $attributes['organization_id'],
            ]),
            'type' => $type,
            'period' => $chargeDate->format('Y-m'),
            'charge_date' => $chargeDate->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'grace_until' => $graceUntil->toDateString(),
            'penalty_date' => $type === Charge::TYPE_PENALTY ? $chargeDate->toDateString() : null,
            'amount' => fake()->randomFloat(2, 200, 30000),
            'meta' => null,
        ];
    }
}
