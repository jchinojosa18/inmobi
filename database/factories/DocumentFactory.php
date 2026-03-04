<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Organization;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'documentable_type' => Unit::class,
            'documentable_id' => Unit::factory()->state(fn (array $attributes): array => [
                'organization_id' => $attributes['organization_id'],
            ]),
            'path' => 'documents/'.fake()->uuid().'.pdf',
            'mime' => fake()->randomElement(['application/pdf', 'image/jpeg', 'image/png']),
            'size' => fake()->numberBetween(15_000, 2_000_000),
            'type' => fake()->randomElement(['evidence', 'receipt', 'contract', 'other']),
            'tags' => ['demo'],
            'meta' => null,
        ];
    }
}
