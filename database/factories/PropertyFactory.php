<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Plaza;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'plaza_id' => function (array $attributes): int {
                $organizationId = (int) ($attributes['organization_id'] ?? 0);
                $organization = Organization::query()->find($organizationId);

                if ($organization === null) {
                    $organization = Organization::factory()->create();
                }

                $defaultPlaza = $organization->defaultPlaza()
                    ->withoutOrganizationScope()
                    ->first();

                if ($defaultPlaza !== null) {
                    return (int) $defaultPlaza->id;
                }

                return (int) $organization->ensureDefaultPlaza()->id;
            },
            'name' => fake()->streetName().' '.fake()->buildingNumber(),
            'code' => strtoupper(fake()->unique()->bothify('PROP-###??')),
            'status' => 'active',
            'kind' => Property::KIND_BUILDING,
            'address' => fake()->address(),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function standaloneHouse(): static
    {
        return $this->state(fn (): array => [
            'kind' => Property::KIND_STANDALONE_HOUSE,
        ]);
    }

    public function inPlaza(Plaza|int $plaza): static
    {
        $plazaId = $plaza instanceof Plaza ? (int) $plaza->id : (int) $plaza;

        return $this->state(fn (): array => [
            'plaza_id' => $plazaId,
        ]);
    }
}
