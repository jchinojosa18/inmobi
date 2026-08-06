<?php

namespace App\Support;

use App\Mail\OrganizationInvitationMail;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class OrganizationInvitationService
{
    /**
     * @var list<string>
     */
    public const ALLOWED_ROLES = ['Admin', 'Capturista', 'Lectura'];

    /**
     * @return array{invitation: OrganizationInvitation, token: string}
     */
    public function createInvitation(
        int $organizationId,
        string $email,
        string $role,
        CarbonImmutable $expiresAt,
        ?int $invitedByUserId
    ): array {
        $normalizedEmail = strtolower(trim($email));
        $role = $this->assertAllowedRole($role);

        Role::findOrCreate($role, 'web');

        $alreadyInOrganization = User::query()
            ->where('organization_id', $organizationId)
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->exists();

        if ($alreadyInOrganization) {
            throw ValidationException::withMessages([
                'email' => __('settings.validation.invitation_email_already_member'),
            ]);
        }

        $hasPendingInvitation = OrganizationInvitation::query()
            ->where('organization_id', $organizationId)
            ->where('email', $normalizedEmail)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->exists();

        if ($hasPendingInvitation) {
            throw ValidationException::withMessages([
                'email' => __('settings.validation.invitation_email_pending'),
            ]);
        }

        $token = Str::random(64);
        $tokenHash = hash('sha256', $token);

        $invitation = DB::transaction(function () use (
            $organizationId,
            $normalizedEmail,
            $role,
            $tokenHash,
            $expiresAt,
            $invitedByUserId
        ): OrganizationInvitation {
            return OrganizationInvitation::query()->create([
                'organization_id' => $organizationId,
                'email' => $normalizedEmail,
                'role' => $role,
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt->toDateTimeString(),
                'invited_by_user_id' => $invitedByUserId,
            ]);
        });

        app(AuditLogger::class)->log(
            action: 'invitation.created',
            auditable: $invitation,
            summary: "Invitación creada para {$normalizedEmail} con rol {$role}",
            meta: [
                'email' => $normalizedEmail,
                'role' => $role,
                'organization_id' => $organizationId,
                'expires_at' => $expiresAt->toDateTimeString(),
            ],
            organizationId: $organizationId,
            actorUserId: $invitedByUserId,
        );

        $invitation->load('organization');
        $invitedBy = $invitedByUserId !== null
            ? User::query()->find($invitedByUserId)
            : null;

        Mail::to($normalizedEmail)->send(new OrganizationInvitationMail(
            invitation: $invitation,
            plainToken: $token,
            invitedBy: $invitedBy,
        ));

        return [
            'invitation' => $invitation,
            'token' => $token,
        ];
    }

    public function findActiveByToken(string $plainToken): ?OrganizationInvitation
    {
        $tokenHash = hash('sha256', $plainToken);

        return OrganizationInvitation::query()
            ->withoutOrganizationScope()
            ->where('token_hash', $tokenHash)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function acceptInvitation(OrganizationInvitation $invitation, User $user): void
    {
        $normalizedEmail = strtolower(trim((string) $user->email));

        if ($normalizedEmail !== strtolower(trim((string) $invitation->email))) {
            throw ValidationException::withMessages([
                'invite' => 'Esta invitación no corresponde al correo autenticado.',
            ]);
        }

        if (! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'invite' => 'La invitación no está disponible (expirada, revocada o ya aceptada).',
            ]);
        }

        $targetOrganizationId = (int) $invitation->organization_id;
        $currentOrganizationId = is_numeric($user->organization_id) ? (int) $user->organization_id : null;
        $targetRole = $this->assertAllowedRole((string) $invitation->role);
        $previousRole = '';

        Role::findOrCreate($targetRole, 'web');

        DB::transaction(function () use (
            $user,
            $invitation,
            $targetOrganizationId,
            $currentOrganizationId,
            $targetRole,
            &$previousRole
        ): void {
            $isCurrentAdmin = $user->hasRole('Admin');
            $currentOrganization = $currentOrganizationId !== null
                ? Organization::query()->find($currentOrganizationId)
                : null;
            $isCurrentOwner = $currentOrganization !== null
                && (int) $currentOrganization->owner_user_id === (int) $user->id;

            if ($currentOrganizationId !== null && $isCurrentAdmin) {
                $movingToAnotherOrganization = $currentOrganizationId !== $targetOrganizationId;
                $demotingInsideSameOrganization = $currentOrganizationId === $targetOrganizationId && $targetRole !== 'Admin';

                if ($movingToAnotherOrganization || $demotingInsideSameOrganization) {
                    if ($currentOrganization !== null && $currentOrganization->adminsCount() <= 1) {
                        throw ValidationException::withMessages([
                            'invite' => 'No puedes quitar al último Admin de la organización actual.',
                        ]);
                    }
                }
            }

            if ($isCurrentOwner && $currentOrganizationId !== $targetOrganizationId) {
                throw ValidationException::withMessages([
                    'invite' => 'Transfiere ownership antes de salir de tu organización actual.',
                ]);
            }

            if ($isCurrentOwner && $targetRole !== 'Admin') {
                throw ValidationException::withMessages([
                    'invite' => 'El owner siempre debe conservar rol Admin.',
                ]);
            }

            $previousRole = (string) ($user->roles()->pluck('name')->first() ?? '');
            $user->organization_id = $targetOrganizationId;
            $user->save();
            $user->syncRoles([$targetRole]);

            $invitation->accepted_at = now();
            $invitation->accepted_by_user_id = $user->id;
            $invitation->save();
        });

        app(AuditLogger::class)->log(
            action: 'invitation.accepted',
            auditable: $invitation,
            summary: "Invitación aceptada por {$user->email} en organización #{$targetOrganizationId}",
            meta: [
                'email' => $user->email,
                'role' => $targetRole,
                'organization_id' => $targetOrganizationId,
            ],
            organizationId: $targetOrganizationId,
            actorUserId: $user->id,
        );

        if ($previousRole !== $targetRole) {
            app(AuditLogger::class)->log(
                action: 'organization.admin_role_changed',
                auditable: $user,
                summary: "Rol actualizado para {$user->email}: {$previousRole} -> {$targetRole}",
                meta: [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'previous_role' => $previousRole,
                    'new_role' => $targetRole,
                ],
                organizationId: $targetOrganizationId,
                actorUserId: $user->id,
            );
        }
    }

    private function assertAllowedRole(string $role): string
    {
        $normalized = trim($role);

        if (! in_array($normalized, self::ALLOWED_ROLES, true)) {
            throw ValidationException::withMessages([
                'role' => __('settings.validation.role_invalid'),
            ]);
        }

        return $normalized;
    }
}
