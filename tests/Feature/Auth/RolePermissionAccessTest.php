<?php

namespace Tests\Feature\Auth;

use App\Livewire\Payments\QuickRegisterModal;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_capturista_can_view_contracts_but_cannot_view_audit(): void
    {
        Role::findOrCreate('Capturista', 'web');
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $user->syncRoles(['Capturista']);

        $this->actingAs($user)
            ->get(route('contracts.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('settings.audit.index'))
            ->assertForbidden();
    }

    public function test_lectura_can_view_reports_but_cannot_create_payment(): void
    {
        Role::findOrCreate('Lectura', 'web');
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $user->syncRoles(['Lectura']);

        $contract = $this->createContractForOrganization($organization);

        $this->actingAs($user)
            ->get(route('reports.flow'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('contracts.payments.create', $contract))
            ->assertForbidden();

        Livewire::actingAs($user)
            ->test(QuickRegisterModal::class)
            ->call('open')
            ->assertForbidden();
    }

    private function createContractForOrganization(Organization $organization): Contract
    {
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);

        $tenant = Tenant::factory()->create([
            'organization_id' => $organization->id,
        ]);

        return Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
        ]);
    }
}
