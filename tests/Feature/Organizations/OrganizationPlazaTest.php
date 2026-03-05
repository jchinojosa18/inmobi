<?php

namespace Tests\Feature\Organizations;

use App\Models\Organization;
use App\Models\Plaza;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationPlazaTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_default_plaza_when_organization_is_created(): void
    {
        $organization = Organization::factory()->create();

        $this->assertDatabaseHas('plazas', [
            'organization_id' => $organization->id,
            'nombre' => Plaza::DEFAULT_NAME,
            'is_default' => true,
        ]);

        $defaultPlaza = $organization->defaultPlaza()
            ->withoutOrganizationScope()
            ->first();

        $this->assertNotNull($defaultPlaza);
        $this->assertSame(Plaza::DEFAULT_NAME, $defaultPlaza->nombre);
        $this->assertSame(1, Plaza::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organization->id)
            ->where('is_default', true)
            ->count());
    }
}
