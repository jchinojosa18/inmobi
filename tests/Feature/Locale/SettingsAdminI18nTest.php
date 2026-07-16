<?php

namespace Tests\Feature\Locale;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SettingsAdminI18nTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_index_renders_english_when_locale_en(): void
    {
        Role::findOrCreate('Admin', 'web');

        $user = User::factory()->create(['locale' => 'en']);
        $user->assignRole('Admin');

        $response = $this->actingAs($user)->get(route('settings.index'));

        $response->assertOk();
        $response->assertSee('Settings', false);
        $response->assertSee('Operational parameters per organization.', false);
        $response->assertSee('Receipt folios', false);
        $response->assertSee('Expense categories', false);
    }

    public function test_admin_system_status_renders_english_when_locale_en(): void
    {
        Role::findOrCreate('Admin', 'web');

        $user = User::factory()->create(['locale' => 'en']);
        $user->assignRole('Admin');

        $response = $this->actingAs($user)->get(route('admin.system'));

        $response->assertOk();
        $response->assertSee('Admin · System', false);
        $response->assertSee('Operational health checklist for production.', false);
        $response->assertSee('Database', false);
        $response->assertSee('Queue Worker', false);
    }
}
