<?php

namespace Tests\Feature\Audit;

use App\Actions\MonthCloses\CloseMonthAction;
use App\Actions\Payments\RegisterContractPaymentAction;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create();
        Role::findOrCreate('Admin', 'web');
        $this->user = User::factory()->create([
            'organization_id' => $this->org->id,
        ]);
        $this->user->assignRole('Admin');
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_register_payment_creates_payment_created_audit_event(): void
    {
        TenantContext::setOrganizationId($this->org->id);
        $contract = $this->makeActiveContract();

        $this->actingAs($this->user);

        app(RegisterContractPaymentAction::class)->execute($contract, [
            'amount' => '1000',
            'method' => 'CASH',
            'paid_at' => now()->toDateString(),
        ]);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'payment.created',
            'organization_id' => $this->org->id,
            'actor_user_id' => $this->user->id,
        ]);
    }

    public function test_close_month_creates_month_closed_audit_event(): void
    {
        $this->actingAs($this->user);
        TenantContext::setOrganizationId($this->org->id);

        app(CloseMonthAction::class)->execute(
            organizationId: $this->org->id,
            userId: $this->user->id,
            month: now()->format('Y-m'),
        );

        $this->assertDatabaseHas('audit_events', [
            'action' => 'month.closed',
            'organization_id' => $this->org->id,
            'actor_user_id' => $this->user->id,
        ]);
    }

    public function test_penalties_command_creates_penalties_run_audit_event(): void
    {
        $this->artisan('inmo:penalties:run', [
            '--date' => now()->toDateString(),
        ]);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'penalties.run',
        ]);
    }

    // ───────── helpers ─────────

    private function makeActiveContract(): Contract
    {
        $property = Property::factory()->create([
            'organization_id' => $this->org->id,
        ]);
        $unit = Unit::factory()->create([
            'organization_id' => $this->org->id,
            'property_id' => $property->id,
        ]);
        $tenant = Tenant::factory()->create([
            'organization_id' => $this->org->id,
        ]);

        return Contract::factory()->create([
            'organization_id' => $this->org->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => Contract::STATUS_ACTIVE,
            'rent_amount' => 5000,
        ]);
    }
}
