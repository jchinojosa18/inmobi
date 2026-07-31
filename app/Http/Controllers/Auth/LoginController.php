<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\OrganizationInvitationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(Request $request, OrganizationInvitationService $invitationService): View
    {
        $inviteToken = trim((string) $request->query('invite', ''));
        $invitation = $inviteToken !== ''
            ? $invitationService->findActiveByToken($inviteToken)
            : null;

        return view('auth.login', [
            'prefillEmail' => $invitation?->email ?? old('email', ''),
        ]);
    }
}
