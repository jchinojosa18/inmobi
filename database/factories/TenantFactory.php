<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Tenant;
use App\Support\TextCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'full_name' => TextCase::upperRequired(fake()->name()),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'ine_clave' => null,
            'status' => 'active',
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
