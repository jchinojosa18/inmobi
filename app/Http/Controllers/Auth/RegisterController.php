<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RegisterController extends Controller
{
    public function show(Request $request, OrganizationInvitationService $invitationService): View
    {
        $invitation = null;
        $inviteToken = trim((string) $request->query('invite', ''));

        if ($inviteToken !== '') {
            $invitation = $invitationService->findActiveByToken($inviteToken);
        }

        return view('auth.register', [
            'inviteToken' => $invitation !== null ? $inviteToken : null,
            'invitation' => $invitation,
        ]);
    }

    public function store(Request $request, OrganizationInvitationService $invitationService): RedirectResponse
    {
        $inviteToken = trim((string) $request->input('invite_token', ''));
        $invitation = $inviteToken !== ''
            ? $invitationService->findActiveByToken($inviteToken)
            : null;

        if ($inviteToken !== '' && $invitation === null) {
            throw ValidationException::withMessages([
                'invite_token' => 'La invitación no es válida o expiró.',
            ]);
        }

        $organizationRules = $invitation === null
            ? ['required', 'string', 'max:160', 'unique:organizations,name']
            : ['nullable', 'string', 'max:160'];

        $validated = $request->validate(
            [
                'organization_name' => $organizationRules,
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
                'invite_token' => ['nullable', 'string'],
            ],
            [
                'organization_name.required' => 'El nombre de la empresa es obligatorio.',
                'organization_name.unique' => 'Ese nombre de empresa ya está registrado.',
                'name.required' => 'El nombre es obligatorio.',
                'email.required' => 'El email es obligatorio.',
                'email.email' => 'El email no es válido.',
                'email.unique' => 'Este email ya está registrado.',
                'password.required' => 'La contraseña es obligatoria.',
                'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
                'password.confirmed' => 'La confirmación de contraseña no coincide.',
            ]
        );

        if ($invitation !== null && strtolower(trim((string) $validated['email'])) !== strtolower((string) $invitation->email)) {
            throw ValidationException::withMessages([
                'email' => 'El email debe coincidir con la invitación recibida.',
            ]);
        }

        $user = DB::transaction(function () use ($validated, $invitationService, $invitation): User {
            if ($invitation !== null) {
                $user = User::query()->create([
                    'organization_id' => $invitation->organization_id,
                    'name' => trim((string) $validated['name']),
                    'email' => strtolower(trim((string) $validated['email'])),
                    'password' => Hash::make((string) $validated['password']),
                ]);

                $invitationService->acceptInvitation($invitation, $user);

                return $user;
            }

            $organization = Organization::query()->create([
                'name' => trim((string) $validated['organization_name']),
            ]);

            $user = User::query()->create([
                'organization_id' => $organization->id,
                'name' => trim((string) $validated['name']),
                'email' => strtolower(trim((string) $validated['email'])),
                'password' => Hash::make((string) $validated['password']),
            ]);

            $organization->owner_user_id = $user->id;
            $organization->save();

            $organization->ensureDefaultPlaza($user->id);
            $organization->defaultPlaza()
                ->withoutOrganizationScope()
                ->update([
                    'nombre' => 'Principal',
                    'timezone' => 'America/Tijuana',
                    'is_default' => true,
                    'created_by_user_id' => $user->id,
                ]);

            Role::findOrCreate('Admin', 'web');
            $user->assignRole('Admin');

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
