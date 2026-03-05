<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Plaza;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plaza>
 */
class PlazaFactory extends Factory
{
    protected $model = Plaza::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'nombre' => fake()->unique()->citySuffix(),
            'ciudad' => fake()->city(),
            'timezone' => config('app.timezone', 'America/Tijuana'),
            'is_default' => false,
            'created_by_user_id' => null,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (): array => [
            'nombre' => Plaza::DEFAULT_NAME,
            'is_default' => true,
        ]);
    }
}
