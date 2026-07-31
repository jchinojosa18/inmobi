<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Expenses\SeedDefaultExpenseCategoriesAction;
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
        $inviteToken = $this->resolveInviteToken($request);
        $invitation = $inviteToken !== ''
            ? $invitationService->findActiveByToken($inviteToken)
            : null;

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
                'invite_token' => __('messages.validation.invite_invalid'),
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
                'organization_name.required' => __('messages.validation.organization_name_required'),
                'organization_name.unique' => __('messages.validation.organization_name_unique'),
                'name.required' => __('messages.validation.name_required'),
                'email.required' => __('messages.validation.email_required'),
                'email.email' => __('messages.validation.email_invalid'),
                'email.unique' => __('messages.validation.email_unique'),
                'password.required' => __('messages.validation.password_required'),
                'password.min' => __('messages.validation.password_min'),
                'password.confirmed' => __('messages.validation.password_confirmed'),
            ]
        );

        if ($invitation !== null && strtolower(trim((string) $validated['email'])) !== strtolower((string) $invitation->email)) {
            throw ValidationException::withMessages([
                'email' => __('messages.validation.invite_email_mismatch'),
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
            app(SeedDefaultExpenseCategoriesAction::class)->execute((int) $organization->id);
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

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return redirect()->intended(route('dashboard'));
    }

    private function resolveInviteToken(Request $request): string
    {
        $token = trim((string) $request->query('invite', ''));

        if ($token !== '') {
            return $token;
        }

        return trim((string) old('invite_token', ''));
    }
}
