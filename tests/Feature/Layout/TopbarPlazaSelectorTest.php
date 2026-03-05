<?php

namespace Tests\Feature\Layout;

use App\Models\Organization;
use App\Models\Plaza;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopbarPlazaSelectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_topbar_hides_plaza_selector_when_organization_has_one_plaza(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSeeText('Plaza:');
        $response->assertDontSee('topbar-plaza-select', false);
    }

    public function test_topbar_shows_plaza_selector_when_organization_has_multiple_plazas(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        Plaza::query()->create([
            'organization_id' => $organization->id,
            'nombre' => 'Ensenada',
            'ciudad' => 'Ensenada',
            'timezone' => 'America/Tijuana',
            'is_default' => false,
            'created_by_user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSeeText('Plaza:');
        $response->assertSee('topbar-plaza-select', false);
        $response->assertSeeText('Todas');
        $response->assertSeeText('Ensenada');
    }
}
