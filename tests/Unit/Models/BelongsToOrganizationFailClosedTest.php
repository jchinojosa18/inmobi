<?php

namespace Tests\Unit\Models;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BelongsToOrganizationFailClosedTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_scoped_charge_query_is_empty_without_tenant_context(): void
    {
        TenantContext::clear();
        $this->assertNull(auth()->user());
        $this->assertTrue(app()->bound('request'));

        $organization = Organization::factory()->create();
        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $tenant = Tenant::factory()->create(['organization_id' => $organization->id]);
        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'rent_amount' => 0,
        ]);

        Charge::query()->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->forceDelete();

        Charge::query()->withoutOrganizationScope()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_OTHER,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 50,
            'meta' => [],
        ]);

        $this->assertSame(0, Charge::query()->count());
        $this->assertTrue(Charge::all()->isEmpty());
        $this->assertSame(1, Charge::query()->withoutOrganizationScope()->count());
    }
}
