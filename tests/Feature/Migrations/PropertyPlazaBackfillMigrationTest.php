<?php

namespace Tests\Feature\Migrations;

use App\Models\Organization;
use App\Models\Plaza;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PropertyPlazaBackfillMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_plaza_migration_backfill_assigns_default_plaza_per_organization(): void
    {
        $migrationPath = database_path('migrations/2026_03_04_200000_add_plaza_id_to_properties_table.php');

        /** @var object{down:callable,up:callable} $migration */
        $migration = require $migrationPath;
        $migration->down();

        $this->assertFalse(Schema::hasColumn('properties', 'plaza_id'));

        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        Plaza::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationB->id)
            ->forceDelete();

        DB::table('properties')->insert([
            [
                'organization_id' => $organizationA->id,
                'name' => 'Prop legacy A',
                'code' => 'LEG-A',
                'status' => 'active',
                'kind' => Property::KIND_BUILDING,
                'address' => null,
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'organization_id' => $organizationB->id,
                'name' => 'Prop legacy B',
                'code' => 'LEG-B',
                'status' => 'active',
                'kind' => Property::KIND_BUILDING,
                'address' => null,
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $migration = require $migrationPath;
        $migration->up();

        $this->assertTrue(Schema::hasColumn('properties', 'plaza_id'));

        $missing = DB::table('properties')->whereNull('plaza_id')->count();
        $this->assertSame(0, $missing);

        $defaultA = (int) Plaza::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationA->id)
            ->where('is_default', true)
            ->value('id');

        $defaultB = (int) Plaza::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationB->id)
            ->where('is_default', true)
            ->value('id');

        $propertyAPlazaId = (int) DB::table('properties')->where('code', 'LEG-A')->value('plaza_id');
        $propertyBPlazaId = (int) DB::table('properties')->where('code', 'LEG-B')->value('plaza_id');

        $this->assertSame($defaultA, $propertyAPlazaId);
        $this->assertSame($defaultB, $propertyBPlazaId);
        $this->assertGreaterThan(0, $defaultB);
    }
}
