<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_role_preview_page(): void
    {
        Role::findOrCreate('Admin', 'web');

        $admin = User::factory()->create();
        $admin->syncRoles(['Admin']);

        $this->actingAs($admin)
            ->get(route('settings.roles'))
            ->assertOk()
            ->assertSeeText('Roles y permisos')
            ->assertSeeText('Capturista');
    }

    public function test_capturista_and_lectura_cannot_view_role_preview_page(): void
    {
        Role::findOrCreate('Capturista', 'web');
        Role::findOrCreate('Lectura', 'web');

        $capturista = User::factory()->create();
        $capturista->syncRoles(['Capturista']);

        $lectura = User::factory()->create();
        $lectura->syncRoles(['Lectura']);

        $this->actingAs($capturista)
            ->get(route('settings.roles'))
            ->assertForbidden();

        $this->actingAs($lectura)
            ->get(route('settings.roles'))
            ->assertForbidden();
    }

    public function test_role_preview_shows_expected_allowed_and_denied_permissions(): void
    {
        Role::findOrCreate('Admin', 'web');

        $admin = User::factory()->create();
        $admin->syncRoles(['Admin']);

        $this->actingAs($admin)
            ->get(route('settings.roles'))
            ->assertOk()
            ->assertSeeText('Capturista')
            ->assertSeeText('payments.create')
            ->assertSeeText('audit.view')
            ->assertSeeText('No permitido');
    }
}
