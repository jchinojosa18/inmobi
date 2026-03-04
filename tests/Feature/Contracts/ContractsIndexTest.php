<?php

namespace Tests\Feature\Contracts;

use App\Models\Contract;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_contracts_index(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $contract = $this->createContractForOrganization(
            $organization,
            tenantName: 'Inquilino Acceso'
        );

        $response = $this->actingAs($user)->get(route('contracts.index'));

        $response->assertOk();
        $response->assertSeeText('Contratos');
        $response->assertSeeText((string) $contract->id);
    }

    public function test_status_filter_shows_only_ended_contracts(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $this->createContractForOrganization(
            $organization,
            tenantName: 'Tenant Active Visible',
            status: Contract::STATUS_ACTIVE
        );

        $this->createContractForOrganization(
            $organization,
            tenantName: 'Tenant Ended Only',
            status: Contract::STATUS_ENDED
        );

        $response = $this->actingAs($user)->get(route('contracts.index', [
            'status' => Contract::STATUS_ENDED,
        ]));

        $response->assertOk();
        $response->assertSeeText('Tenant Ended Only');
        $response->assertDontSeeText('Tenant Active Visible');
    }

    public function test_search_by_tenant_name_returns_matching_contract(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $this->createContractForOrganization(
            $organization,
            tenantName: 'Mariana Busqueda Contract',
            status: Contract::STATUS_ACTIVE
        );

        $this->createContractForOrganization(
            $organization,
            tenantName: 'Pedro Sin Match',
            status: Contract::STATUS_ACTIVE
        );

        $response = $this->actingAs($user)->get(route('contracts.index', [
            'q' => 'Mariana',
            'status' => 'all',
        ]));

        $response->assertOk();
        $response->assertSeeText('Mariana Busqueda Contract');
        $response->assertDontSeeText('Pedro Sin Match');
    }

    private function createContractForOrganization(
        Organization $organization,
        string $tenantName,
        string $status = Contract::STATUS_ACTIVE
    ): Contract {
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);

        $tenant = Tenant::factory()->create([
            'organization_id' => $organization->id,
            'full_name' => $tenantName,
            'email' => strtolower(str_replace(' ', '.', $tenantName)).'@example.test',
            'phone' => '555-000-0000',
        ]);

        return Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => $status,
            'starts_at' => '2026-01-01',
            'ends_at' => $status === Contract::STATUS_ENDED ? '2026-02-01' : null,
        ]);
    }
}
