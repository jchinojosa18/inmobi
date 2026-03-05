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

    public ?string $lastInvitationLink = null;

    /**
     * @var array<int, string>
     */
    public array $userRoles = [];

    public string $transferOwnerUserId = '';

    /**
     * @var list<string>
     */
    private array $allowedRoles = ['Admin', 'Capturista', 'Lectura'];

    public function mount(): void
    {
        $this->assertAdminCanEdit();
    }

    public function createInvitation(OrganizationInvitationService $invitationService): void
    {
        $this->assertAdminCanEdit();

        $validated = $this->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:Admin,Capturista,Lectura'],
            'expiresInDays' => ['required', 'integer', 'min:1', 'max:30'],
        ], [
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El email no es válido.',
            'role.required' => 'Selecciona un rol.',
            'role.in' => 'El rol seleccionado no es válido.',
            'expiresInDays.required' => 'Define los días de expiración.',
            'expiresInDays.integer' => 'La expiración debe ser un número.',
            'expiresInDays.min' => 'La expiración mínima es 1 día.',
            'expiresInDays.max' => 'La expiración máxima es 30 días.',
        ]);

        $expiresAt = CarbonImmutable::now('America/Tijuana')->addDays((int) $validated['expiresInDays']);

        try {
            $created = $invitationService->createInvitation(
                organizationId: (int) auth()->user()?->organization_id,
                email: (string) $validated['email'],
                role: (string) $validated['role'],
                expiresAt: $expiresAt,
                invitedByUserId: auth()->id() !== null ? (int) auth()->id() : null,
            );
        } catch (ValidationException $exception) {
            $message = (string) ($exception->errors()['email'][0] ?? 'No se pudo crear la invitación.');
            $this->addError('email', $message);

            return;
        }

        $token = $created['token'];
        $this->lastInvitationLink = route('invitations.accept', ['token' => $token]);
        $this->reset(['email', 'role', 'expiresInDays']);
        $this->role = 'Capturista';
        $this->expiresInDays = '7';
        session()->flash('success', 'Invitación creada correctamente.');
    }

    public function revokeInvitation(int $invitationId): void
    {
        $this->assertAdminCanEdit();

        $invitation = OrganizationInvitation::query()
            ->where('organization_id', (int) auth()->user()?->organization_id)
            ->whereKey($invitationId)
            ->firstOrFail();

        if ($invitation->accepted_at !== null || $invitation->revoked_at !== null) {
            return;
        }

        $invitation->revoked_at = now();
        $invitation->save();

        session()->flash('success', 'Invitación revocada.');
    }

    public function updateUserRole(int $userId): void
    {
        $this->assertAdminCanEdit();

        $organization = $this->currentOrganization();
        $targetRole = $this->userRoles[$userId] ?? '';
        if (! in_array($targetRole, $this->allowedRoles, true)) {
            $this->addError("userRoles.{$userId}", 'Rol inválido.');

            return;
        }

        $user = User::query()
            ->where('organization_id', (int) $organization->id)
            ->findOrFail($userId);

        if ((int) $organization->owner_user_id === (int) $user->id && $targetRole !== 'Admin') {
            $this->addError("userRoles.{$userId}", 'El owner siempre debe conservar rol Admin.');

            return;
        }

        if ($user->hasRole('Admin') && $targetRole !== 'Admin' && $organization->adminsCount() <= 1) {
            $this->addError("userRoles.{$userId}", 'No puedes quitar al último Admin de la organización.');

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

        session()->flash('success', 'Rol actualizado.');
    }

    public function removeUser(int $userId): void
    {
        $this->assertAdminCanEdit();

        $organization = $this->currentOrganization();
        $organizationId = (int) $organization->id;
        $user = User::query()
            ->where('organization_id', $organizationId)
            ->findOrFail($userId);

        if ((int) $organization->owner_user_id === (int) $user->id) {
            $this->addError('remove_user', 'No puedes quitar al usuario owner de la organización.');

            return;
        }

        if ($user->hasRole('Admin') && $organization->adminsCount() <= 1) {
            $this->addError('remove_user', 'No puedes quitar al último Admin de la organización.');

            return;
        }

        $user->organization_id = null;
        $user->save();
        $user->syncRoles([]);

        session()->flash('success', 'Usuario removido de la organización.');
    }

    public function transferOwnership(): void
    {
        $this->assertAdminCanEdit();

        $organization = $this->currentOrganization();
        $actor = auth()->user();

        if ((int) ($actor?->id ?? 0) !== (int) $organization->owner_user_id) {
            abort(403);
        }

        $targetUserId = (int) $this->transferOwnerUserId;
        if ($targetUserId <= 0) {
            $this->addError('transferOwnerUserId', 'Selecciona un usuario para transferir ownership.');

            return;
        }

        $target = User::query()
            ->where('organization_id', (int) $organization->id)
            ->find($targetUserId);

        if ($target === null) {
            $this->addError('transferOwnerUserId', 'El usuario seleccionado no pertenece a esta organización.');

            return;
        }

        if ((int) $target->id === (int) $organization->owner_user_id) {
            session()->flash('success', 'Ese usuario ya es el owner actual.');

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
        session()->flash('success', 'Ownership transferido correctamente.');
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
            'title' => 'Usuarios e invitaciones',
        ]);
    }

    private function currentOrganization(): Organization
    {
        return Organization::query()->findOrFail((int) auth()->user()?->organization_id);
    }

    private function assertAdminCanEdit(): void
    {
        if (! (auth()->user()?->hasRole('Admin') ?? false)) {
            abort(403);
        }
    }
}
