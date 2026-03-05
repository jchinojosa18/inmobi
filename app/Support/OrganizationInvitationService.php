<?php

namespace App\Support;

use App\Models\OrganizationInvitation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class OrganizationInvitationService
{
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
        $role = trim($role);

        Role::findOrCreate($role, 'web');

        $alreadyInOrganization = User::query()
            ->where('organization_id', $organizationId)
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->exists();

        if ($alreadyInOrganization) {
            throw ValidationException::withMessages([
                'email' => 'Ese correo ya pertenece a esta empresa.',
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
            OrganizationInvitation::query()
                ->where('organization_id', $organizationId)
                ->where('email', $normalizedEmail)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);

            return OrganizationInvitation::query()->create([
                'organization_id' => $organizationId,
                'email' => $normalizedEmail,
                'role' => $role,
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt->toDateTimeString(),
                'invited_by_user_id' => $invitedByUserId,
            ]);
        });

        return [
            'invitation' => $invitation,
            'token' => $token,
        ];
    }

    public function findActiveByToken(string $plainToken): ?OrganizationInvitation
    {
        $tokenHash = hash('sha256', $plainToken);

        return OrganizationInvitation::query()
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
        $targetRole = trim((string) $invitation->role);

        Role::findOrCreate($targetRole, 'web');

        DB::transaction(function () use (
            $user,
            $invitation,
            $targetOrganizationId,
            $currentOrganizationId,
            $targetRole
        ): void {
            $isCurrentAdmin = $user->hasRole('Admin');

            if ($currentOrganizationId !== null && $isCurrentAdmin) {
                $movingToAnotherOrganization = $currentOrganizationId !== $targetOrganizationId;
                $demotingInsideSameOrganization = $currentOrganizationId === $targetOrganizationId && $targetRole !== 'Admin';

                if ($movingToAnotherOrganization || $demotingInsideSameOrganization) {
                    $adminCount = User::query()
                        ->where('organization_id', $currentOrganizationId)
                        ->role('Admin')
                        ->count();

                    if ($adminCount <= 1) {
                        throw ValidationException::withMessages([
                            'invite' => 'No puedes quitar al último Admin de la organización actual.',
                        ]);
                    }
                }
            }

            $user->organization_id = $targetOrganizationId;
            $user->save();
            $user->syncRoles([$targetRole]);

            $invitation->accepted_at = now();
            $invitation->accepted_by_user_id = $user->id;
            $invitation->save();
        });
    }
}
