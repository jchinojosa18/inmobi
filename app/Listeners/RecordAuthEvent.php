<?php

namespace App\Listeners;

use App\Models\AuthEvent;
use App\Support\TenantContext;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class RecordAuthEvent
{
    public function handleLogin(Login $event): void
    {
        try {
            $user = $event->user;

            AuthEvent::create([
                'organization_id' => $user->organization_id ?? null,
                'user_id' => $user->id,
                'email' => $user->email,
                'event' => 'login_success',
                'occurred_at' => now(),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'plaza_id' => TenantContext::currentPlazaId(),
                'meta' => ['guard' => $event->guard],
            ]);
        } catch (\Throwable) {
            // Never block authentication due to audit logging failure.
        }
    }

    public function handleFailed(Failed $event): void
    {
        try {
            $email = is_array($event->credentials)
                ? ($event->credentials['email'] ?? null)
                : null;

            AuthEvent::create([
                'organization_id' => null,
                'user_id' => $event->user?->id,
                'email' => $email,
                'event' => 'login_failed',
                'occurred_at' => now(),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'plaza_id' => null,
                'meta' => ['guard' => $event->guard],
            ]);
        } catch (\Throwable) {
            // Never block authentication due to audit logging failure.
        }
    }

    public function handleLogout(Logout $event): void
    {
        try {
            $user = $event->user;
            if ($user === null) {
                return;
            }

            AuthEvent::create([
                'organization_id' => $user->organization_id ?? null,
                'user_id' => $user->id,
                'email' => $user->email,
                'event' => 'logout',
                'occurred_at' => now(),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'plaza_id' => TenantContext::currentPlazaId(),
                'meta' => ['guard' => $event->guard],
            ]);
        } catch (\Throwable) {
            // Never block authentication due to audit logging failure.
        }
    }
}
