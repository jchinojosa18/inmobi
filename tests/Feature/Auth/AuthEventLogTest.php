<?php

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthEventLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_records_login_success_event(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $org->id,
            'email' => 'audit-login@example.com',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'audit-login@example.com',
            'password' => 'password',
        ]);

        $this->assertDatabaseHas('auth_events', [
            'user_id' => $user->id,
            'event' => 'login_success',
            'email' => 'audit-login@example.com',
        ]);
    }

    public function test_failed_login_records_login_failed_event(): void
    {
        $this->post('/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrong',
        ]);

        $this->assertDatabaseHas('auth_events', [
            'email' => 'nonexistent@example.com',
            'event' => 'login_failed',
        ]);
    }

    public function test_logout_records_logout_event(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $org->id,
        ]);

        $this->actingAs($user)->post('/logout');

        $this->assertDatabaseHas('auth_events', [
            'user_id' => $user->id,
            'event' => 'logout',
        ]);
    }

    public function test_login_and_logout_both_record_events_for_user(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $org->id,
            'email' => 'count-test@example.com',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'count-test@example.com',
            'password' => 'password',
        ]);

        $this->assertDatabaseHas('auth_events', ['user_id' => $user->id, 'event' => 'login_success']);

        $this->actingAs($user)->post('/logout');

        $this->assertDatabaseHas('auth_events', ['user_id' => $user->id, 'event' => 'logout']);
    }
}
