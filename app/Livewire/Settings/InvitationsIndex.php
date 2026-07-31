<?php

namespace App\Livewire\Settings;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\OrganizationInvitationService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class InvitationsIndex extends Component
{
    public string $email = '';

    public string $role = 'Capturista';

    public string $expiresInDays = '7';

    /**
     * @var array<int, string>
     */
    public array $userRoles = [];

    public string $transferOwnerUserId = '';

    public bool $showRemoveUserConfirm = false;

    public ?int $pendingRemoveUserId = null;

    public ?string $pendingRemoveUserName = null;

    /**
     * @var list<string>
     */
    private array $allowedRoles = ['Admin', 'Capturista', 'Lectura'];

    public function mount(): void
    {
        $this->assertCanManageInvitations();
    }

    public function createInvitation(OrganizationInvitationService $invitationService): void
    {
        $this->assertCanManageInvitations();

        $validated = $this->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:Admin,Capturista,Lectura'],
            'expiresInDays' => ['required', 'integer', 'min:1', 'max:30'],
        ], [
            'email.required' => __('settings.validation.invitation_email_required'),
            'email.email' => __('settings.validation.invitation_email_invalid'),
            'role.required' => __('settings.validation.role_required'),
            'role.in' => __('settings.validation.role_invalid'),
            'expiresInDays.required' => __('settings.validation.expires_required'),
            'expiresInDays.integer' => __('settings.validation.expires_integer'),
            'expiresInDays.min' => __('settings.validation.expires_min'),
            'expiresInDays.max' => __('settings.validation.expires_max'),
        ]);

        $expiresAt = CarbonImmutable::now('America/Tijuana')->addDays((int) $validated['expiresInDays']);

        try {
            $invitationService->createInvitation(
                organizationId: (int) auth()->user()?->organization_id,
                email: (string) $validated['email'],
                role: (string) $validated['role'],
                expiresAt: $expiresAt,
                invitedByUserId: auth()->id() !== null ? (int) auth()->id() : null,
            );
        } catch (ValidationException $exception) {
            $message = (string) ($exception->errors()['email'][0] ?? __('settings.validation.invitation_create_failed'));
            $this->addError('email', $message);

            return;
        }

        $this->reset(['email', 'role', 'expiresInDays']);
        $this->role = 'Capturista';
        $this->expiresInDays = '7';
        session()->flash('success', __('settings.flash.invitation_created'));
    }

    public function revokeInvitation(int $invitationId): void
    {
        $this->assertCanManageInvitations();

        $invitation = OrganizationInvitation::query()
            ->where('organization_id', (int) auth()->user()?->organization_id)
            ->whereKey($invitationId)
            ->firstOrFail();

        if ($invitation->accepted_at !== null || $invitation->revoked_at !== null) {
            return;
        }

        $invitation->revoked_at = now();
        $invitation->save();

        session()->flash('success', __('settings.flash.invitation_revoked'));
    }

    public function updateUserRole(int $userId): void
    {
        $this->assertCanManageUsers();

        $organization = $this->currentOrganization();
        $targetRole = $this->userRoles[$userId] ?? '';
        if (! in_array($targetRole, $this->allowedRoles, true)) {
            $this->addError("userRoles.{$userId}", __('settings.validation.user_role_invalid'));

            return;
        }

        $user = User::query()
            ->where('organization_id', (int) $organization->id)
            ->findOrFail($userId);

        if ((int) $organization->owner_user_id === (int) $user->id) {
            $this->addError("userRoles.{$userId}", __('settings.validation.owner_role_locked'));

            return;
        }

        if ($user->hasRole('Admin') && $targetRole !== 'Admin' && $organization->adminsCount() <= 1) {
            $this->addError("userRoles.{$userId}", __('settings.validation.cannot_remove_last_admin'));

            return;
        }

        $currentRole = (string) ($user->roles()->pluck('name')->first() ?? 'Lectura');

        Role::findOrCreate($targetRole, 'web');
        $user->syncRoles([$targetRole]);

        if ($currentRole !== $targetRole) {
            app(AuditLogger::class)->log(
                action: 'organization.admin_role_changed',
                auditable: $user,
                summary: "Rol actualizado para {$user->email}: {$currentRole} -> {$targetRole}",
                meta: [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'previous_role' => $currentRole,
                    'new_role' => $targetRole,
                ],
                organizationId: (int) $organization->id,
                actorUserId: auth()->id() !== null ? (int) auth()->id() : null,
            );
        }

        session()->flash('success', __('settings.flash.role_updated'));
    }

    public function confirmRemoveUser(int $userId): void
    {
        $this->assertCanManageUsers();

        $organization = $this->currentOrganization();
        $user = User::query()
            ->where('organization_id', (int) $organization->id)
            ->findOrFail($userId);

        if ((int) $organization->owner_user_id === (int) $user->id) {
            $this->addError('remove_user', __('settings.validation.cannot_remove_owner'));

            return;
        }

        $this->pendingRemoveUserId = $user->id;
        $this->pendingRemoveUserName = $user->name;
        $this->showRemoveUserConfirm = true;
    }

    public function cancelRemoveUserConfirm(): void
    {
        $this->showRemoveUserConfirm = false;
        $this->pendingRemoveUserId = null;
        $this->pendingRemoveUserName = null;
    }

    public function executeRemoveUserConfirm(): void
    {
        if ($this->pendingRemoveUserId === null) {
            return;
        }

        $this->removeUser($this->pendingRemoveUserId);
        $this->cancelRemoveUserConfirm();
    }

    public function removeUser(int $userId): void
    {
        $this->assertCanManageUsers();

        $organization = $this->currentOrganization();
        $organizationId = (int) $organization->id;
        $user = User::query()
            ->where('organization_id', $organizationId)
            ->findOrFail($userId);

        if ((int) $organization->owner_user_id === (int) $user->id) {
            $this->addError('remove_user', __('settings.validation.cannot_remove_owner'));

            return;
        }

        if ($user->hasRole('Admin') && $organization->adminsCount() <= 1) {
            $this->addError('remove_user', __('settings.validation.cannot_remove_last_admin'));

            return;
        }

        $user->organization_id = null;
        $user->save();
        $user->syncRoles([]);

        session()->flash('success', __('settings.flash.user_removed'));
    }

    public function transferOwnership(): void
    {
        $this->assertCanManageUsers();

        $organization = $this->currentOrganization();
        $actor = auth()->user();

        if ((int) ($actor?->id ?? 0) !== (int) $organization->owner_user_id) {
            abort(403);
        }

        $targetUserId = (int) $this->transferOwnerUserId;
        if ($targetUserId <= 0) {
            $this->addError('transferOwnerUserId', __('settings.validation.transfer_user_required'));

            return;
        }

        $target = User::query()
            ->where('organization_id', (int) $organization->id)
            ->find($targetUserId);

        if ($target === null) {
            $this->addError('transferOwnerUserId', __('settings.validation.transfer_user_invalid'));

            return;
        }

        if ((int) $target->id === (int) $organization->owner_user_id) {
            session()->flash('success', __('settings.flash.already_owner'));

            return;
        }

        $previousOwnerId = (int) $organization->owner_user_id;
        $previousOwner = User::query()->find($previousOwnerId);

        DB::transaction(function () use ($organization, $target): void {
            Role::findOrCreate('Admin', 'web');
            if (! $target->hasRole('Admin')) {
                $target->assignRole('Admin');
            }

            $organization->owner_user_id = $target->id;
            $organization->save();
        });

        app(AuditLogger::class)->log(
            action: 'organization.owner_transferred',
            auditable: $organization,
            summary: "Ownership transferido a {$target->email}",
            meta: [
                'previous_owner_user_id' => $previousOwner?->id,
                'previous_owner_email' => $previousOwner?->email,
                'new_owner_user_id' => $target->id,
                'new_owner_email' => $target->email,
            ],
            organizationId: (int) $organization->id,
            actorUserId: auth()->id() !== null ? (int) auth()->id() : null,
        );

        $this->transferOwnerUserId = (string) $target->id;
        session()->flash('success', __('settings.flash.ownership_transferred'));
    }

    public function render(): View
    {
        $organization = $this->currentOrganization()->load('ownerUser:id,name,email');
        $organizationId = (int) $organization->id;

        $users = User::query()
            ->with('roles:id,name')
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->get();

        foreach ($users as $user) {
            if (! array_key_exists($user->id, $this->userRoles)) {
                $this->userRoles[$user->id] = (string) ($user->roles->first()?->name ?? 'Lectura');
            }
        }

        if ($this->transferOwnerUserId === '' && $users->isNotEmpty()) {
            $candidate = $users->firstWhere('id', '!=', $organization->owner_user_id) ?? $users->first();
            $this->transferOwnerUserId = $candidate !== null ? (string) $candidate->id : '';
        }

        $pendingInvitations = OrganizationInvitation::query()
            ->where('organization_id', $organizationId)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.settings.invitations-index', [
            'organization' => $organization,
            'users' => $users,
            'pendingInvitations' => $pendingInvitations,
            'allowedRoles' => $this->allowedRoles,
            'canTransferOwnership' => (int) auth()->id() === (int) $organization->owner_user_id,
        ])->layout('layouts.app', [
            'title' => __('settings.invitations_title'),
        ]);
    }

    private function currentOrganization(): Organization
    {
        return Organization::query()->findOrFail((int) auth()->user()?->organization_id);
    }

    private function assertCanManageInvitations(): void
    {
        if (! (auth()->user()?->can('invitations.manage') ?? false)) {
            abort(403);
        }
    }

    private function assertCanManageUsers(): void
    {
        if (! (auth()->user()?->can('users.manage') ?? false)) {
            abort(403);
        }
    }
}
