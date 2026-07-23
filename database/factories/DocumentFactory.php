<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Document;
use App\Models\Organization;
use App\Models\Unit;
use App\Support\ContractDocumentCategory;
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
            'category' => null,
            'tags' => ['demo'],
            'meta' => null,
        ];
    }

    public function forContractCategory(ContractDocumentCategory $category): static
    {
        return $this->state(fn (): array => [
            'category' => $category->value,
            'documentable_type' => Contract::class,
            'type' => 'CONTRACT_DOCUMENT',
            'mime' => 'application/pdf',
        ]);
    }
}
