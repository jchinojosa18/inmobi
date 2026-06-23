<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El email no es válido.',
        ]);

        Password::sendResetLink($request->only('email'));

        return back()->with(
            'status',
            'Si el email está registrado, recibirás un enlace para restablecer tu contraseña.'
        );
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El email no es válido.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()
                ->route('login')
                ->with('status', 'Tu contraseña fue actualizada. Ya puedes iniciar sesión.');
        }

        return back()->withErrors([
            'email' => match ($status) {
                Password::INVALID_TOKEN => 'El enlace de recuperación no es válido o expiró.',
                Password::INVALID_USER => 'No encontramos un usuario con ese email.',
                default => 'No pudimos restablecer tu contraseña. Intenta solicitar un enlace nuevo.',
            },
        ]);
    }
}
