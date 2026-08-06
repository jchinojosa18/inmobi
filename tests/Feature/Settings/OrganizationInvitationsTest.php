<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\InvitationsIndex;
use App\Mail\OrganizationInvitationMail;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Support\OrganizationInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrganizationInvitationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_invitation_from_settings_screen(): void
    {
        Mail::fake();

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

        Mail::assertSent(OrganizationInvitationMail::class, function (OrganizationInvitationMail $mail) use ($organization) {
            return $mail->hasTo('nuevo.usuario@test.dev')
                && $mail->envelope()->from->name === $organization->name;
        });
    }

    public function test_it_blocks_invitation_when_email_already_belongs_to_organization(): void
    {
        Mail::fake();

        [$organization, $admin] = $this->createOrganizationAdminPair();
        User::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'existing.member@test.dev',
        ]);

        $invitationsBefore = OrganizationInvitation::query()->count();

        Livewire::actingAs($admin)
            ->test(InvitationsIndex::class)
            ->set('email', 'existing.member@test.dev')
            ->set('role', 'Lectura')
            ->set('expiresInDays', '7')
            ->call('createInvitation')
            ->assertHasErrors('email');

        $this->assertSame($invitationsBefore, OrganizationInvitation::query()->count());
        Mail::assertNothingSent();
    }

    public function test_it_blocks_invitation_when_pending_invitation_exists(): void
    {
        Mail::fake();

        [$organization, $admin] = $this->createOrganizationAdminPair();
        $this->createInvitationToken($organization->id, 'pending.invite@test.dev', 'Lectura', $admin->id);

        Livewire::actingAs($admin)
            ->test(InvitationsIndex::class)
            ->set('email', 'pending.invite@test.dev')
            ->set('role', 'Capturista')
            ->set('expiresInDays', '7')
            ->call('createInvitation')
            ->assertHasErrors('email');

        $this->assertSame(1, OrganizationInvitation::query()
            ->where('organization_id', $organization->id)
            ->where('email', 'pending.invite@test.dev')
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->count());

        Mail::assertSentCount(1);
    }

    public function test_non_admin_cannot_access_invitations_screen(): void
    {
        Role::findOrCreate('Lectura', 'web');
        [$organization] = $this->createOrganizationAdminPair();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $user->syncRoles(['Lectura']);

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

        $this->followingRedirects()
            ->get(route('register', ['invite' => $token]))
            ->assertOk()
            ->assertSee('value="guest.invite@test.dev"', false)
            ->assertSee('disabled', false)
            ->assertSee('type="hidden" name="email"', false);
    }

    public function test_register_page_keeps_invitation_email_after_validation_error(): void
    {
        [$organization, $admin] = $this->createOrganizationAdminPair();
        $token = $this->createInvitationToken($organization->id, 'retry.invite@test.dev', 'Lectura', $admin->id);

        $response = $this->from(route('register', ['invite' => $token]))
            ->post(route('register.store'), [
                'invite_token' => $token,
                'name' => '',
                'email' => 'retry.invite@test.dev',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

        $response->assertRedirect(route('register', ['invite' => $token]));

        $this->get(route('register', ['invite' => $token]))
            ->assertOk()
            ->assertSee('value="retry.invite@test.dev"', false)
            ->assertSee('type="hidden" name="email"', false)
            ->assertSee('name="invite_token"', false)
            ->assertSee($token, false);
    }

    public function test_invitation_link_prefills_login_email_for_existing_account(): void
    {
        [$organization, $admin] = $this->createOrganizationAdminPair();
        User::factory()->create([
            'organization_id' => null,
            'email' => 'existing.guest@test.dev',
        ]);
        $token = $this->createInvitationToken($organization->id, 'existing.guest@test.dev', 'Lectura', $admin->id);

        $response = $this->get(route('invitations.accept', ['token' => $token]));

        $response->assertRedirect(route('login', ['invite' => $token]));
        $response->assertSessionHas('status');

        $this->get(route('login', ['invite' => $token]))
            ->assertOk()
            ->assertSee('value="existing.guest@test.dev"', false);
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

    public function test_owner_role_select_is_hidden_and_cannot_be_updated(): void
    {
        [$organization, $owner] = $this->createOrganizationAdminPair();

        $secondAdmin = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $secondAdmin->assignRole('Admin');

        Livewire::actingAs($owner)
            ->test(InvitationsIndex::class)
            ->assertDontSeeHtml('wire:model="userRoles.'.$owner->id.'"')
            ->set("userRoles.{$owner->id}", 'Lectura')
            ->call('updateUserRole', $owner->id)
            ->assertHasErrors("userRoles.{$owner->id}");

        $owner->refresh();
        $this->assertTrue($owner->hasRole('Admin'));
    }

    public function test_owner_can_transfer_ownership_and_audit_event_is_created(): void
    {
        [$organization, $owner] = $this->createOrganizationAdminPair();

        $newOwner = User::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'new-owner@test.dev',
        ]);
        $newOwner->assignRole('Capturista');

        Livewire::actingAs($owner)
            ->test(InvitationsIndex::class)
            ->set('transferOwnerUserId', (string) $newOwner->id)
            ->call('transferOwnership')
            ->assertHasNoErrors();

        $organization->refresh();
        $newOwner->refresh();

        $this->assertSame((int) $newOwner->id, (int) $organization->owner_user_id);
        $this->assertTrue($newOwner->hasRole('Admin'));

        $this->assertDatabaseHas('audit_events', [
            'organization_id' => $organization->id,
            'actor_user_id' => $owner->id,
            'action' => 'organization.owner_transferred',
        ]);
    }

    public function test_owner_transfer_blocks_user_from_other_organization(): void
    {
        [$organization, $owner] = $this->createOrganizationAdminPair();
        $outsideUser = User::factory()->create([
            'organization_id' => Organization::factory()->create()->id,
        ]);
        $outsideUser->assignRole('Admin');

        Livewire::actingAs($owner)
            ->test(InvitationsIndex::class)
            ->set('transferOwnerUserId', (string) $outsideUser->id)
            ->call('transferOwnership')
            ->assertHasErrors('transferOwnerUserId');

        $organization->refresh();
        $this->assertSame((int) $owner->id, (int) $organization->owner_user_id);
    }

    public function test_owner_remove_button_is_hidden_and_direct_remove_is_blocked(): void
    {
        [$organization, $owner] = $this->createOrganizationAdminPair();

        Livewire::actingAs($owner)
            ->test(InvitationsIndex::class)
            ->assertDontSeeHtml('wire:click="confirmRemoveUser('.$owner->id.')"')
            ->call('removeUser', $owner->id)
            ->assertHasErrors('remove_user');

        $owner->refresh();
        $this->assertSame((int) $organization->id, (int) $owner->organization_id);
    }

    public function test_remove_user_requires_confirmation_before_removing_member(): void
    {
        [$organization, $owner] = $this->createOrganizationAdminPair();

        $member = User::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'member.remove@test.dev',
            'name' => 'Member Remove',
        ]);
        $member->assignRole('Lectura');

        Livewire::actingAs($owner)
            ->test(InvitationsIndex::class)
            ->call('confirmRemoveUser', $member->id)
            ->assertSet('showRemoveUserConfirm', true)
            ->assertSet('pendingRemoveUserId', $member->id)
            ->assertSee(__('settings.remove_user_body', ['name' => 'Member Remove']))
            ->call('cancelRemoveUserConfirm')
            ->assertSet('showRemoveUserConfirm', false)
            ->call('confirmRemoveUser', $member->id)
            ->call('executeRemoveUserConfirm')
            ->assertSet('showRemoveUserConfirm', false)
            ->assertHasNoErrors();

        $member->refresh();
        $this->assertNull($member->organization_id);
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
