<?php

namespace Tests\Feature\Brand;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AxisBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_shows_axis_brand_not_inmo_admin(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('AXIS', false);
        $response->assertSee('images/brand/axis-mark.svg', false);
        $response->assertDontSee('Inmo Admin', false);
    }

    public function test_app_layout_includes_favicon_links_and_axis_document_title(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('<title>Dashboard operativo | AXIS</title>', false);
        $response->assertSee('rel="icon"', false);
        $response->assertSee('favicon.svg', false);
    }

    public function test_login_page_shows_axis_brand_and_favicon(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('AXIS', false);
        $response->assertSee('favicon.svg', false);
        $response->assertSee('<title>Login | AXIS</title>', false);
        $response->assertDontSee('Inmo Admin', false);
    }

    public function test_favicon_ico_file_is_non_empty(): void
    {
        $path = public_path('favicon.ico');

        $this->assertFileExists($path);
        $this->assertGreaterThan(0, filesize($path));
    }
}
