<?php

namespace Tests\Feature\Tenants;

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UppercaseTenantFullNamesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_uppercases_existing_tenant_full_names(): void
    {
        $organization = Organization::factory()->create();

        $tenantId = DB::table('tenants')->insertGetId([
            'organization_id' => $organization->id,
            'full_name' => 'juan pérez gómez',
            'email' => null,
            'phone' => null,
            'status' => 'active',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_08_04_100000_uppercase_tenant_full_names.php');
        $migration->up();

        $this->assertSame(
            'JUAN PÉREZ GÓMEZ',
            DB::table('tenants')->where('id', $tenantId)->value('full_name')
        );
    }
}
