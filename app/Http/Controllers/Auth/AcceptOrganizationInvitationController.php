<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\OrganizationInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AcceptOrganizationInvitationController extends Controller
{
    public function __invoke(
        Request $request,
        string $token,
        OrganizationInvitationService $invitationService
    ): RedirectResponse {
        $invitation = $invitationService->findActiveByToken($token);

        if ($invitation === null) {
            return redirect()->route('login')->withErrors([
                'invite' => 'La invitación no es válida o expiró.',
            ]);
        }

        $user = $request->user();

        if ($user === null) {
            $hasAccount = User::query()
                ->whereRaw('LOWER(email) = ?', [strtolower(trim((string) $invitation->email))])
                ->exists();

            if ($hasAccount) {
                return redirect()
                    ->guest(route('login'))
                    ->with('status', 'Inicia sesión para aceptar tu invitación.');
            }

            return redirect()->route('register', ['invite' => $token]);
        }

        try {
            $invitationService->acceptInvitation($invitation, $user);
        } catch (ValidationException $exception) {
            $messages = $exception->errors();
            $message = (string) (($messages['invite'][0] ?? null) ?: 'No se pudo aceptar la invitación.');

            return redirect()->route('dashboard')->withErrors([
                'invite' => $message,
            ]);
        }

        return redirect()->route('dashboard')->with('success', 'Invitación aceptada. Ya formas parte de la empresa.');
    }
}
