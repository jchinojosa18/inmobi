<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_system_status_page(): void
    {
        Role::findOrCreate('Admin', 'web');

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $response = $this->actingAs($admin)->get(route('admin.system'));

        $response->assertOk();
        $response->assertSeeText('Admin · System');
        $response->assertSeeText('APP_ENV');
        $response->assertSeeText('PHP Version');
    }

    public function test_non_admin_cannot_access_system_status_page(): void
    {
        Role::findOrCreate('Admin', 'web');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.system'));

        $response->assertForbidden();
    }
}
