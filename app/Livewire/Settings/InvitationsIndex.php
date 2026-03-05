<?php

namespace App\Livewire\Settings;

use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Support\OrganizationInvitationService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
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

        $targetRole = $this->userRoles[$userId] ?? '';
        if (! in_array($targetRole, $this->allowedRoles, true)) {
            $this->addError("userRoles.{$userId}", 'Rol inválido.');

            return;
        }

        $user = User::query()
            ->where('organization_id', (int) auth()->user()?->organization_id)
            ->findOrFail($userId);

        if ($user->hasRole('Admin') && $targetRole !== 'Admin' && $this->adminCount() <= 1) {
            $this->addError("userRoles.{$userId}", 'No puedes quitar al último Admin de la organización.');

            return;
        }

        Role::findOrCreate($targetRole, 'web');
        $user->syncRoles([$targetRole]);

        session()->flash('success', 'Rol actualizado.');
    }

    public function removeUser(int $userId): void
    {
        $this->assertAdminCanEdit();

        $organizationId = (int) auth()->user()?->organization_id;
        $organization = \App\Models\Organization::query()->findOrFail($organizationId);
        $user = User::query()
            ->where('organization_id', $organizationId)
            ->findOrFail($userId);

        if ((int) $organization->owner_user_id === (int) $user->id) {
            $this->addError('remove_user', 'No puedes quitar al usuario owner de la organización.');

            return;
        }

        if ($user->hasRole('Admin') && $this->adminCount() <= 1) {
            $this->addError('remove_user', 'No puedes quitar al último Admin de la organización.');

            return;
        }

        $user->organization_id = null;
        $user->save();
        $user->syncRoles([]);

        session()->flash('success', 'Usuario removido de la organización.');
    }

    public function render(): View
    {
        $organizationId = (int) auth()->user()?->organization_id;

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

        $pendingInvitations = OrganizationInvitation::query()
            ->where('organization_id', $organizationId)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.settings.invitations-index', [
            'users' => $users,
            'pendingInvitations' => $pendingInvitations,
            'allowedRoles' => $this->allowedRoles,
        ])->layout('layouts.app', [
            'title' => 'Usuarios e invitaciones',
        ]);
    }

    private function adminCount(): int
    {
        return User::query()
            ->where('organization_id', (int) auth()->user()?->organization_id)
            ->role('Admin')
            ->count();
    }

    private function assertAdminCanEdit(): void
    {
        if (! (auth()->user()?->hasRole('Admin') ?? false)) {
            abort(403);
        }
    }
}
