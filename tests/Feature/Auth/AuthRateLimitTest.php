<?php

namespace Tests\Feature\Auth;

use App\Models\AuthEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_sixth_failed_login_attempt_returns_429(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->post(route('login.store'), [
                'email' => 'ratelimit-login@test.dev',
                'password' => 'invalid-password',
            ])->assertStatus(302);
        }

        $response = $this->post(route('login.store'), [
            'email' => 'ratelimit-login@test.dev',
            'password' => 'invalid-password',
        ]);

        $response->assertStatus(429);
        $response->assertSeeText('Demasiados intentos. Intenta de nuevo en');
    }

    public function test_throttled_login_attempt_is_logged_in_auth_events_with_meta(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->post(route('login.store'), [
                'email' => 'throttle-log@test.dev',
                'password' => 'invalid-password',
            ]);
        }

        $event = AuthEvent::query()
            ->where('event', 'login_failed')
            ->where('email', 'throttle-log@test.dev')
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertTrue((bool) data_get($event?->meta, 'throttled'));
    }

    public function test_register_rate_limit_returns_429_after_exceeding_limit(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->post(route('register.store'), [
                'organization_name' => "Reg Org {$i}",
                'name' => "User {$i}",
                'email' => "register-rate-{$i}@test.dev",
                'password' => 'password',
                'password_confirmation' => 'password',
            ])->assertStatus(302);
        }

        $response = $this->post(route('register.store'), [
            'organization_name' => 'Reg Org 4',
            'name' => 'User 4',
            'email' => 'register-rate-4@test.dev',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(429);
        $response->assertSeeText('Demasiados intentos. Intenta de nuevo en');
    }

    public function test_verification_resend_rate_limit_returns_429_after_exceeding_limit(): void
    {
        $user = User::factory()->unverified()->create();

        for ($i = 1; $i <= 3; $i++) {
            $this->actingAs($user)
                ->post(route('verification.send'))
                ->assertStatus(302);
        }

        $response = $this->actingAs($user)
            ->post(route('verification.send'));

        $response->assertStatus(429);
        $response->assertSeeText('Demasiados intentos. Intenta de nuevo en');
    }
}
