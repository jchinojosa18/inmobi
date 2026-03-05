<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\InvitationsIndex;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrganizationInvitationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_invitation_from_settings_screen(): void
    {
        [$organization, $admin] = $this->createOrganizationAdminPair();

        Livewire::actingAs($admin)
            ->test(InvitationsIndex::class)
            ->set('email', 'nuevo.usuario@test.dev')
            ->set('role', 'Capturista')
            ->set('expiresInDays', '10')
            ->call('createInvitation');

        $this->assertDatabaseHas('organization_invitations', [
            'organization_id' => $organization->id,
            'email' => 'nuevo.usuario@test.dev',
            'role' => 'Capturista',
            'accepted_at' => null,
            'revoked_at' => null,
        ]);
    }

    public function test_non_admin_cannot_access_invitations_screen(): void
    {
        [$organization] = $this->createOrganizationAdminPair();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($user)
            ->get(route('settings.invitations.index'))
            ->assertForbidden();
    }

    public function test_invitation_link_sends_guest_without_account_to_register_with_token(): void
    {
        [$organization, $admin] = $this->createOrganizationAdminPair();
        $token = $this->createInvitationToken($organization->id, 'guest.invite@test.dev', 'Lectura', $admin->id);

        $response = $this->get(route('invitations.accept', ['token' => $token]));

        $response->assertRedirect(route('register', ['invite' => $token]));
    }

    public function test_invitation_link_sends_guest_with_existing_account_to_login(): void
    {
        [$organization, $admin] = $this->createOrganizationAdminPair();
        User::factory()->create([
            'organization_id' => null,
            'email' => 'existing.guest@test.dev',
        ]);
        $token = $this->createInvitationToken($organization->id, 'existing.guest@test.dev', 'Lectura', $admin->id);

        $response = $this->get(route('invitations.accept', ['token' => $token]));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');
    }

    public function test_register_with_invitation_joins_existing_org_without_creating_new_org(): void
    {
        [$organization, $admin] = $this->createOrganizationAdminPair();
        $token = $this->createInvitationToken($organization->id, 'joiner@test.dev', 'Lectura', $admin->id);

        $organizationsBefore = Organization::query()->count();

        $response = $this->post(route('register.store'), [
            'invite_token' => $token,
            'name' => 'Joiner User',
            'email' => 'joiner@test.dev',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertSame($organizationsBefore, Organization::query()->count());

        $user = User::query()->where('email', 'joiner@test.dev')->firstOrFail();
        $this->assertSame((int) $organization->id, (int) $user->organization_id);
        $this->assertTrue($user->hasRole('Lectura'));

        $this->assertDatabaseHas('organization_invitations', [
            'organization_id' => $organization->id,
            'email' => 'joiner@test.dev',
            'accepted_by_user_id' => $user->id,
        ]);
    }

    public function test_existing_user_accepts_invitation_and_joins_target_org(): void
    {
        [$targetOrg, $targetAdmin] = $this->createOrganizationAdminPair();
        $existingUser = User::factory()->create([
            'organization_id' => null,
            'email' => 'existing@test.dev',
        ]);

        $token = $this->createInvitationToken($targetOrg->id, 'existing@test.dev', 'Capturista', $targetAdmin->id);

        $response = $this->actingAs($existingUser)
            ->get(route('invitations.accept', ['token' => $token]));

        $response->assertRedirect(route('dashboard'));

        $existingUser->refresh();
        $this->assertSame((int) $targetOrg->id, (int) $existingUser->organization_id);
        $this->assertTrue($existingUser->hasRole('Capturista'));
    }

    public function test_it_blocks_removing_last_admin_via_invitation_acceptance(): void
    {
        Role::findOrCreate('Admin', 'web');

        $sourceOrg = Organization::factory()->create(['name' => 'Source Org']);
        $targetOrg = Organization::factory()->create(['name' => 'Target Org']);

        $sourceAdmin = User::factory()->create([
            'organization_id' => $sourceOrg->id,
            'email' => 'last.admin@source.dev',
        ]);
        $sourceAdmin->assignRole('Admin');
        $sourceOrg->owner_user_id = $sourceAdmin->id;
        $sourceOrg->save();

        $targetAdmin = User::factory()->create([
            'organization_id' => $targetOrg->id,
            'email' => 'admin@target.dev',
        ]);
        $targetAdmin->assignRole('Admin');
        $targetOrg->owner_user_id = $targetAdmin->id;
        $targetOrg->save();

        $token = $this->createInvitationToken($targetOrg->id, 'last.admin@source.dev', 'Capturista', $targetAdmin->id);

        $response = $this->actingAs($sourceAdmin)
            ->get(route('invitations.accept', ['token' => $token]));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHasErrors('invite');

        $sourceAdmin->refresh();
        $this->assertSame((int) $sourceOrg->id, (int) $sourceAdmin->organization_id);
        $this->assertTrue($sourceAdmin->hasRole('Admin'));

        $this->assertDatabaseHas('organization_invitations', [
            'organization_id' => $targetOrg->id,
            'email' => 'last.admin@source.dev',
            'accepted_at' => null,
        ]);
    }

    public function test_it_blocks_demoting_last_admin_from_settings_users_screen(): void
    {
        [$organization, $admin] = $this->createOrganizationAdminPair();

        Livewire::actingAs($admin)
            ->test(InvitationsIndex::class)
            ->set("userRoles.{$admin->id}", 'Lectura')
            ->call('updateUserRole', $admin->id)
            ->assertHasErrors("userRoles.{$admin->id}");

        $admin->refresh();
        $this->assertTrue($admin->hasRole('Admin'));
        $this->assertSame((int) $organization->id, (int) $admin->organization_id);
    }

    private function createOrganizationAdminPair(): array
    {
        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Capturista', 'web');
        Role::findOrCreate('Lectura', 'web');

        $organization = Organization::factory()->create();
        $admin = User::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'admin.'.uniqid().'@test.dev',
        ]);
        $admin->assignRole('Admin');
        $organization->owner_user_id = $admin->id;
        $organization->save();

        return [$organization, $admin];
    }

    private function createInvitationToken(
        int $organizationId,
        string $email,
        string $role,
        int $invitedByUserId
    ): string {
        $result = app(OrganizationInvitationService::class)->createInvitation(
            organizationId: $organizationId,
            email: $email,
            role: $role,
            expiresAt: now('America/Tijuana')->addDays(7)->toImmutable(),
            invitedByUserId: $invitedByUserId
        );

        return $result['token'];
    }
}
