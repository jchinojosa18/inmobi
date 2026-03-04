<?php

namespace Database\Factories;

use App\Models\ExpenseCategory;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseCategory>
 */
class ExpenseCategoryFactory extends Factory
{
    protected $model = ExpenseCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => strtoupper(fake()->unique()->words(2, true)),
            'is_active' => true,
        ];
    }
}
