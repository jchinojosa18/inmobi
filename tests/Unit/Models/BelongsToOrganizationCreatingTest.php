<?php

namespace Tests\Unit\Models;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BelongsToOrganizationCreatingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_authenticated_create_overwrites_foreign_organization_id(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $user = User::factory()->create(['organization_id' => $orgA->id]);
        $this->actingAs($user);
        TenantContext::setOrganizationId($orgA->id);

        $property = Property::factory()->create(['organization_id' => $orgA->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $orgA->id,
            'property_id' => $property->id,
        ]);
        $tenant = Tenant::factory()->create(['organization_id' => $orgA->id]);
        $contract = Contract::factory()->create([
            'organization_id' => $orgA->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'rent_amount' => 0,
        ]);

        $charge = Charge::query()->withoutOrganizationScope()->create([
            'organization_id' => $orgB->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_OTHER,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 50,
            'meta' => [],
        ]);

        $this->assertSame($orgA->id, (int) $charge->organization_id);
    }

    public function test_unauthenticated_create_keeps_explicit_organization_id(): void
    {
        TenantContext::clear();
        $this->assertNull(auth()->user());

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

        $charge = Charge::query()->withoutOrganizationScope()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_OTHER,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 50,
            'meta' => [],
        ]);

        $this->assertSame($organization->id, (int) $charge->organization_id);
    }
}
