<?php

namespace Tests\Feature\Console;

use App\Models\AuditEvent;
use App\Models\AuthEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneLogsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prunes_only_old_records_with_days_window(): void
    {
        AuthEvent::query()->create([
            'event' => 'login_failed',
            'email' => 'old-auth@test.dev',
            'occurred_at' => now()->subDays(120),
        ]);
        AuthEvent::query()->create([
            'event' => 'login_success',
            'email' => 'recent-auth@test.dev',
            'occurred_at' => now()->subDays(10),
        ]);

        AuditEvent::query()->create([
            'action' => 'payment.created',
            'summary' => 'old-audit',
            'occurred_at' => now()->subDays(250),
        ]);
        AuditEvent::query()->create([
            'action' => 'month.closed',
            'summary' => 'recent-audit',
            'occurred_at' => now()->subDays(20),
        ]);

        $this->artisan('inmo:logs:prune', [
            '--auth-days' => 90,
            '--audit-days' => 180,
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('Auth events deleted: 1')
            ->expectsOutputToContain('Audit events deleted: 1');

        $this->assertDatabaseMissing('auth_events', ['email' => 'old-auth@test.dev']);
        $this->assertDatabaseHas('auth_events', ['email' => 'recent-auth@test.dev']);
        $this->assertDatabaseMissing('audit_events', ['summary' => 'old-audit']);
        $this->assertDatabaseHas('audit_events', ['summary' => 'recent-audit']);
    }

    public function test_dry_run_reports_counts_without_deleting(): void
    {
        AuthEvent::query()->create([
            'event' => 'login_failed',
            'email' => 'dry-auth@test.dev',
            'occurred_at' => now()->subDays(120),
        ]);
        AuditEvent::query()->create([
            'action' => 'payment.created',
            'summary' => 'dry-audit',
            'occurred_at' => now()->subDays(250),
        ]);

        $this->artisan('inmo:logs:prune', [
            '--auth-days' => 90,
            '--audit-days' => 180,
            '--dry-run' => true,
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('Auth events deleted: 0 (dry-run, would delete: 1)')
            ->expectsOutputToContain('Audit events deleted: 0 (dry-run, would delete: 1)');

        $this->assertDatabaseHas('auth_events', ['email' => 'dry-auth@test.dev']);
        $this->assertDatabaseHas('audit_events', ['summary' => 'dry-audit']);
    }

    public function test_before_option_prunes_all_records_before_given_date(): void
    {
        AuthEvent::query()->create([
            'event' => 'login_failed',
            'email' => 'before-old-auth@test.dev',
            'occurred_at' => '2025-12-31 12:00:00',
        ]);
        AuthEvent::query()->create([
            'event' => 'login_success',
            'email' => 'before-recent-auth@test.dev',
            'occurred_at' => '2026-01-02 12:00:00',
        ]);

        AuditEvent::query()->create([
            'action' => 'payment.created',
            'summary' => 'before-old-audit',
            'occurred_at' => '2025-12-15 09:00:00',
        ]);
        AuditEvent::query()->create([
            'action' => 'payment.created',
            'summary' => 'before-recent-audit',
            'occurred_at' => '2026-01-03 09:00:00',
        ]);

        $this->artisan('inmo:logs:prune', [
            '--before' => '2026-01-01',
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('Auth events deleted: 1')
            ->expectsOutputToContain('Audit events deleted: 1');

        $this->assertDatabaseMissing('auth_events', ['email' => 'before-old-auth@test.dev']);
        $this->assertDatabaseHas('auth_events', ['email' => 'before-recent-auth@test.dev']);
        $this->assertDatabaseMissing('audit_events', ['summary' => 'before-old-audit']);
        $this->assertDatabaseHas('audit_events', ['summary' => 'before-recent-audit']);
    }
}
