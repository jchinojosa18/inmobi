<?php

namespace Tests\Feature\Locale;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSwitcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_to_spanish_when_no_preference(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'));

        $this->assertSame('es', app()->getLocale());
    }

    public function test_user_locale_column_takes_priority(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)->get(route('dashboard'));

        $this->assertSame('en', app()->getLocale());
    }

    public function test_session_locale_used_for_guest(): void
    {
        $response = $this->withSession(['locale' => 'en'])->get(route('login'));

        $response->assertOk();
        $this->assertSame('en', app()->getLocale());
    }

    public function test_guest_can_switch_locale_via_post(): void
    {
        $response = $this->post(route('locale.update'), ['locale' => 'en']);

        $response->assertRedirect();
        $response->assertSessionHas('locale', 'en');
    }

    public function test_authenticated_user_persists_locale_to_database(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('locale.update'), ['locale' => 'en'])
            ->assertRedirect();

        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_invalid_locale_is_rejected(): void
    {
        $this->post(route('locale.update'), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');
    }

    public function test_sidebar_renders_english_when_locale_is_en(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('Collections', false);
        $response->assertSee('Sign out', false);
        $response->assertSee('lang="en"', false);
    }

    public function test_login_page_renders_english_when_session_locale_is_en(): void
    {
        $response = $this->withSession(['locale' => 'en'])->get(route('login'));

        $response->assertOk();
        $response->assertSee('Sign in', false);
        $response->assertSee('Forgot your password?', false);
    }

    public function test_dashboard_renders_english_when_locale_is_en(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Operational Dashboard', false);
        $response->assertSee('Operating income this month', false);
    }

    public function test_topbar_search_renders_english_when_locale_is_en(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Open quick search', false);
    }
}
