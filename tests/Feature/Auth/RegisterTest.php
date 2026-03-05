<?php

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Models\Plaza;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_organization_user_default_plaza_and_admin_role(): void
    {
        $response = $this->post(route('register.store'), $this->validPayload());

        $response->assertRedirect(route('dashboard'));

        /** @var User $user */
        $user = User::query()->where('email', 'owner@acme.test')->firstOrFail();
        $organization = Organization::query()->findOrFail((int) $user->organization_id);

        $this->assertAuthenticatedAs($user);
        $this->assertSame('Inmobiliaria Acme', $organization->name);
        $this->assertSame((int) $user->id, (int) $organization->owner_user_id);
        $this->assertTrue($user->hasRole('Admin'));

        $defaultPlaza = Plaza::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organization->id)
            ->where('is_default', true)
            ->first();

        $this->assertNotNull($defaultPlaza);
        $this->assertSame('Principal', $defaultPlaza?->nombre);
        $this->assertSame('America/Tijuana', $defaultPlaza?->timezone);
    }

    public function test_register_creates_one_default_plaza_for_new_organization(): void
    {
        $this->post(route('register.store'), $this->validPayload());

        $user = User::query()->where('email', 'owner@acme.test')->firstOrFail();
        $organizationId = (int) $user->organization_id;

        $allPlazas = Plaza::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->count();
        $defaultPlazas = Plaza::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->where('is_default', true)
            ->count();

        $this->assertSame(1, $allPlazas);
        $this->assertSame(1, $defaultPlazas);
    }

    public function test_user_can_access_dashboard_after_register_once_verified(): void
    {
        $response = $this->post(route('register.store'), $this->validPayload());

        $response
            ->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'owner@acme.test')->firstOrFail();
        $user->markEmailAsVerified();

        $this->actingAs($user->fresh());
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Dashboard');
    }

    /**
     * @return array<string, string>
     */
    private function validPayload(): array
    {
        Role::findOrCreate('Admin', 'web');

        return [
            'organization_name' => 'Inmobiliaria Acme',
            'name' => 'Owner Acme',
            'email' => 'owner@acme.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ];
    }
}
