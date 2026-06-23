<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_links_to_password_reset_route(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('password.request'), false);
    }

    public function test_guest_can_view_forgot_password_form(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('¿Olvidaste tu contraseña?');
    }

    public function test_guest_can_request_password_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'reset@example.com',
        ]);

        $this->post(route('password.email'), [
            'email' => 'reset@example.com',
        ])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_guest_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => 'old-password',
        ]);

        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $user->refresh();

        $this->assertTrue(Hash::check('new-password', $user->password));
    }
}
