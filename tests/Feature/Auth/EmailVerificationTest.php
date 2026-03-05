<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_cannot_access_dashboard(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_can_access_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_resend_verification_notification_works(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_sends_verification_email_for_new_account(): void
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'organization_name' => 'Verif Org',
            'name' => 'Verif User',
            'email' => 'verify.new@test.dev',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'verify.new@test.dev')->firstOrFail();

        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
