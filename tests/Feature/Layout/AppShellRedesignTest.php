<?php

namespace Tests\Feature\Layout;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppShellRedesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_layout_renders_dark_sidebar_and_search(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Inmo Admin', false);
        $response->assertSee('bg-slate-900', false);
        $response->assertSee('Buscar', false);
        $response->assertSee('Dashboard operativo', false);
    }

    public function test_cobranza_page_renders_with_redesigned_shell(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('cobranza.index'));

        $response->assertOk();
        $response->assertSee('Cobranza', false);
        $response->assertSee('bg-slate-900', false);
    }

    public function test_contracts_page_uses_spanish_filter_labels(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('contracts.index'));

        $response->assertOk();
        $response->assertSee('Activos', false);
        $response->assertSee('Finalizados', false);
        $response->assertSee('Todos', false);
        $response->assertDontSee('>Active<', false);
    }
}
