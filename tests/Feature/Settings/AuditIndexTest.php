<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\AuditIndex;
use App\Models\AuditEvent;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_audit_index(): void
    {
        $org = Organization::factory()->create();
        Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create(['organization_id' => $org->id]);
        $admin->assignRole('Admin');

        $this->actingAs($admin)
            ->get(route('settings.audit.index'))
            ->assertOk();
    }

    public function test_non_admin_cannot_access_audit_index(): void
    {
        $org = Organization::factory()->create();
        Role::findOrCreate('Viewer', 'web');
        $user = User::factory()->create(['organization_id' => $org->id]);
        $user->syncRoles(['Viewer']);

        $this->actingAs($user)
            ->get(route('settings.audit.index'))
            ->assertForbidden();
    }

    public function test_admin_can_filter_audit_events_by_action(): void
    {
        $org = Organization::factory()->create();
        Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create(['organization_id' => $org->id]);
        $admin->assignRole('Admin');

        AuditEvent::create([
            'organization_id' => $org->id,
            'action' => 'payment.created',
            'summary' => 'Pago registrado REC-001 $1000',
            'occurred_at' => now(),
        ]);

        AuditEvent::create([
            'organization_id' => $org->id,
            'action' => 'month.closed',
            'summary' => 'Mes cerrado: 2026-03',
            'occurred_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(AuditIndex::class)
            ->set('action', 'payment.created')
            ->assertSee('payment.created')
            ->assertDontSee('Mes cerrado');
    }

    public function test_admin_can_export_audit_csv(): void
    {
        $org = Organization::factory()->create();
        Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create(['organization_id' => $org->id]);
        $admin->assignRole('Admin');

        AuditEvent::create([
            'organization_id' => $org->id,
            'action' => 'payment.created',
            'summary' => 'Pago test',
            'occurred_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('settings.audit.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_non_admin_cannot_export_audit_csv(): void
    {
        $org = Organization::factory()->create();
        Role::findOrCreate('Viewer', 'web');
        $user = User::factory()->create(['organization_id' => $org->id]);
        $user->syncRoles(['Viewer']);

        $this->actingAs($user)
            ->get(route('settings.audit.export'))
            ->assertForbidden();
    }
}
