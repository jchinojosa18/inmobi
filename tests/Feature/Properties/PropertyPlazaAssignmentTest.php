<?php

namespace Tests\Feature\Properties;

use App\Models\Organization;
use App\Models\Plaza;
use App\Models\Property;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PropertyPlazaAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_created_without_plaza_id_is_auto_assigned_to_default_plaza(): void
    {
        $organization = Organization::factory()->create();

        $property = Property::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Torre Auto Plaza',
            'code' => 'AUTO-PLAZA',
            'status' => 'active',
            'kind' => Property::KIND_BUILDING,
            'address' => 'Av. Central 100',
            'notes' => null,
        ]);

        $defaultPlazaId = (int) Plaza::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organization->id)
            ->where('is_default', true)
            ->value('id');

        $this->assertNotNull($property->plaza_id);
        $this->assertSame($defaultPlazaId, (int) $property->plaza_id);
    }

    public function test_raw_insert_without_plaza_id_is_rejected_by_database_constraint(): void
    {
        $organization = Organization::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('properties')->insert([
            'organization_id' => $organization->id,
            'name' => 'Legacy sin plaza',
            'code' => 'LEGACY-SP',
            'status' => 'active',
            'kind' => Property::KIND_BUILDING,
            'address' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
