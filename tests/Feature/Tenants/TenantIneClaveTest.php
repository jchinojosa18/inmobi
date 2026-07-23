<?php

namespace Tests\Feature\Tenants;

use App\Models\Organization;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIneClaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_can_persist_optional_ine_clave(): void
    {
        $organization = Organization::factory()->create();

        $tenant = Tenant::query()->create([
            'organization_id' => $organization->id,
            'full_name' => 'Ana Pérez',
            'status' => 'active',
            'ine_clave' => 'ABCD120101HDFRRN09',
        ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'ine_clave' => 'ABCD120101HDFRRN09',
        ]);

        $without = Tenant::query()->create([
            'organization_id' => $organization->id,
            'full_name' => 'Luis Gómez',
            'status' => 'active',
            'ine_clave' => null,
        ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $without->id,
            'ine_clave' => null,
        ]);
    }
}
