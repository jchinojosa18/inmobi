<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\PlazasIndex;
use App\Models\Organization;
use App\Models\Plaza;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlazasManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_create_plaza(): void
    {
        Role::findOrCreate('Admin', 'web');
        $organization = Organization::factory()->create();
        $admin = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $admin->assignRole('Admin');

        $this->actingAs($admin)
            ->get(route('settings.plazas.index'))
            ->assertOk()
            ->assertSeeText('Plazas');

        Livewire::actingAs($admin)
            ->test(PlazasIndex::class)
            ->call('startCreate')
            ->set('nombre', 'Ensenada')
            ->set('ciudad', 'Ensenada')
            ->set('timezone', 'America/Tijuana')
            ->set('isDefault', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('plazas', [
            'organization_id' => $organization->id,
            'nombre' => 'Ensenada',
            'ciudad' => 'Ensenada',
            'timezone' => 'America/Tijuana',
        ]);
    }

    public function test_non_admin_cannot_access_plazas_settings(): void
    {
        Role::findOrCreate('Lectura', 'web');

        $user = User::factory()->create();
        $user->syncRoles(['Lectura']);

        $this->actingAs($user)
            ->get(route('settings.plazas.index'))
            ->assertForbidden();
    }

    public function test_marking_new_default_keeps_only_one_default(): void
    {
        Role::findOrCreate('Admin', 'web');
        $organization = Organization::factory()->create();
        $admin = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $admin->assignRole('Admin');

        $defaultPlaza = $organization->defaultPlaza()
            ->withoutOrganizationScope()
            ->firstOrFail();

        $newPlaza = Plaza::query()->create([
            'organization_id' => $organization->id,
            'nombre' => 'Mexicali',
            'ciudad' => 'Mexicali',
            'timezone' => 'America/Tijuana',
            'is_default' => false,
            'created_by_user_id' => $admin->id,
        ]);

        Livewire::actingAs($admin)
            ->test(PlazasIndex::class)
            ->call('markAsDefault', $newPlaza->id)
            ->assertHasNoErrors();

        $this->assertSame(1, Plaza::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organization->id)
            ->where('is_default', true)
            ->count());

        $this->assertTrue($newPlaza->fresh()->is_default);
        $this->assertFalse((bool) $defaultPlaza->fresh()->is_default);
    }

    public function test_delete_confirmation_escapes_plaza_name(): void
    {
        Role::findOrCreate('Admin', 'web');
        $organization = Organization::factory()->create();
        $admin = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $admin->assignRole('Admin');

        $plaza = Plaza::query()->create([
            'organization_id' => $organization->id,
            'nombre' => '<script>alert(1)</script>',
            'ciudad' => 'Tijuana',
            'timezone' => 'America/Tijuana',
            'is_default' => false,
            'created_by_user_id' => $admin->id,
        ]);

        Livewire::actingAs($admin)
            ->test(PlazasIndex::class)
            ->call('confirmDelete', $plaza->id)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_cannot_delete_plaza_that_still_has_properties(): void
    {
        Role::findOrCreate('Admin', 'web');
        $organization = Organization::factory()->create();
        $admin = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $admin->assignRole('Admin');

        $defaultPlaza = $organization->defaultPlaza()
            ->withoutOrganizationScope()
            ->firstOrFail();

        $extraPlaza = Plaza::query()->create([
            'organization_id' => $organization->id,
            'nombre' => 'Ensenada',
            'ciudad' => 'Ensenada',
            'timezone' => 'America/Tijuana',
            'is_default' => false,
            'created_by_user_id' => $admin->id,
        ]);

        \App\Models\Property::factory()->create([
            'organization_id' => $organization->id,
            'plaza_id' => $extraPlaza->id,
        ]);

        Livewire::actingAs($admin)
            ->test(PlazasIndex::class)
            ->call('delete', $extraPlaza->id)
            ->assertHasErrors(['delete']);

        $this->assertNull($extraPlaza->fresh()->deleted_at);
        $this->assertDatabaseHas('plazas', [
            'id' => $defaultPlaza->id,
            'deleted_at' => null,
        ]);
    }

    public function test_can_delete_empty_non_default_plaza(): void
    {
        Role::findOrCreate('Admin', 'web');
        $organization = Organization::factory()->create();
        $admin = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $admin->assignRole('Admin');

        $extraPlaza = Plaza::query()->create([
            'organization_id' => $organization->id,
            'nombre' => 'Vacía',
            'ciudad' => 'Vacía',
            'timezone' => 'America/Tijuana',
            'is_default' => false,
            'created_by_user_id' => $admin->id,
        ]);

        Livewire::actingAs($admin)
            ->test(PlazasIndex::class)
            ->call('delete', $extraPlaza->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted('plazas', ['id' => $extraPlaza->id]);
    }
}
