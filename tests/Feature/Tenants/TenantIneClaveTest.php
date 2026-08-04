<?php

namespace Tests\Feature\Tenants;

use App\Livewire\Tenants\Index;
use App\Livewire\Tenants\Show;
use App\Models\Organization;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    public function test_index_creates_tenant_without_ine_clave(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $organization->id]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('startCreate')
            ->set('full_name', 'Sin Ine')
            ->set('formStatus', 'active')
            ->set('ine_clave', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tenants', [
            'organization_id' => $organization->id,
            'full_name' => 'SIN INE',
            'ine_clave' => null,
        ]);
    }

    public function test_index_creates_tenant_with_normalized_ine_clave(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $organization->id]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('startCreate')
            ->set('full_name', 'Con Ine')
            ->set('formStatus', 'active')
            ->set('ine_clave', ' abcd120101hdfrrn09 ')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tenants', [
            'organization_id' => $organization->id,
            'full_name' => 'CON INE',
            'ine_clave' => 'ABCD120101HDFRRN09',
        ]);
    }

    public function test_index_rejects_invalid_ine_clave_format(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $organization->id]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('startCreate')
            ->set('full_name', 'Bad Ine')
            ->set('formStatus', 'active')
            ->set('ine_clave', 'TOO-SHORT')
            ->call('save')
            ->assertHasErrors(['ine_clave']);
    }

    public function test_index_rejects_duplicate_ine_clave_in_same_organization(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $organization->id]);
        Tenant::factory()->create([
            'organization_id' => $organization->id,
            'ine_clave' => 'ABCD120101HDFRRN09',
        ]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('startCreate')
            ->set('full_name', 'Dup Ine')
            ->set('formStatus', 'active')
            ->set('ine_clave', 'ABCD120101HDFRRN09')
            ->call('save')
            ->assertHasErrors(['ine_clave']);
    }

    public function test_index_allows_same_ine_clave_in_different_organization(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        Tenant::factory()->create([
            'organization_id' => $orgA->id,
            'ine_clave' => 'ABCD120101HDFRRN09',
        ]);
        $adminB = User::factory()->create(['organization_id' => $orgB->id]);

        Livewire::actingAs($adminB)
            ->test(Index::class)
            ->call('startCreate')
            ->set('full_name', 'Other Org')
            ->set('formStatus', 'active')
            ->set('ine_clave', 'ABCD120101HDFRRN09')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tenants', [
            'organization_id' => $orgB->id,
            'full_name' => 'OTHER ORG',
            'ine_clave' => 'ABCD120101HDFRRN09',
        ]);
    }

    public function test_index_edit_can_clear_ine_clave(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $tenant = Tenant::factory()->create([
            'organization_id' => $organization->id,
            'ine_clave' => 'ABCD120101HDFRRN09',
        ]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('startEdit', $tenant->id)
            ->set('ine_clave', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'ine_clave' => null,
        ]);
    }

    public function test_kardex_shows_ine_clave_or_n_a(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $withClave = Tenant::factory()->create([
            'organization_id' => $organization->id,
            'ine_clave' => 'ABCD120101HDFRRN09',
        ]);
        $withoutClave = Tenant::factory()->create([
            'organization_id' => $organization->id,
            'ine_clave' => null,
        ]);

        Livewire::actingAs($admin)
            ->test(Show::class, ['tenant' => $withClave])
            ->assertSeeText('ABCD120101HDFRRN09')
            ->assertSeeText(__('catalog.tenants.ine_clave'));

        Livewire::actingAs($admin)
            ->test(Show::class, ['tenant' => $withoutClave])
            ->assertSeeText(__('common.n_a'));
    }

    public function test_show_edit_updates_and_clears_ine_clave(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $tenant = Tenant::factory()->create([
            'organization_id' => $organization->id,
            'ine_clave' => null,
        ]);

        Livewire::actingAs($admin)
            ->test(Show::class, ['tenant' => $tenant])
            ->call('startEdit')
            ->set('ine_clave', 'abcd120101hdfrrn09')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'ine_clave' => 'ABCD120101HDFRRN09',
        ]);

        Livewire::actingAs($admin)
            ->test(Show::class, ['tenant' => $tenant->fresh()])
            ->call('startEdit')
            ->set('ine_clave', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'ine_clave' => null,
        ]);
    }
}
