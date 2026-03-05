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
        $user = User::factory()->create();

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
}
