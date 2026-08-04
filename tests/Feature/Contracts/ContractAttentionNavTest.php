<?php

namespace Tests\Feature\Contracts;

use App\Models\Contract;
use App\Models\Organization;
use App\Models\Plaza;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\ContractAttentionNav;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractAttentionNavTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_summary_counts_attention_and_flags_expired(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');

        $organization = Organization::factory()->create();
        TenantContext::setOrganizationId($organization->id);
        $user = User::factory()->create(['organization_id' => $organization->id]);

        $this->createContractForOrganization($organization->id, 'Vencido', '2026-07-20');
        $this->createContractForOrganization($organization->id, 'Por Vencer', '2026-08-20');
        $this->createContractForOrganization($organization->id, 'Lejos', '2026-12-01');
        $this->createContractForOrganization(
            $organization->id,
            'Terminado Pasado',
            '2026-07-01',
            Contract::STATUS_ENDED,
        );

        $this->actingAs($user);

        $summary = ContractAttentionNav::summary();

        $this->assertSame(2, $summary['count']);
        $this->assertTrue($summary['has_expired']);
    }

    public function test_sidebar_shows_red_badge_when_expired_present(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');

        $organization = Organization::factory()->create();
        TenantContext::setOrganizationId($organization->id);
        $user = User::factory()->create(['organization_id' => $organization->id]);

        $this->createContractForOrganization($organization->id, 'Vencido Sidebar', '2026-07-20');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('contract-attention-badge', false);
        $response->assertSee(route('contracts.index'), false);
        $response->assertDontSee(route('contracts.index', ['status' => 'attention'], false));
        $response->assertSee('bg-rose-600', false);
    }

    public function test_sidebar_shows_amber_badge_when_only_expiring(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');

        $organization = Organization::factory()->create();
        TenantContext::setOrganizationId($organization->id);
        $user = User::factory()->create(['organization_id' => $organization->id]);

        $this->createContractForOrganization($organization->id, 'Por Vencer Sidebar', '2026-08-20');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('contract-attention-badge', false);
        $response->assertSee(route('contracts.index'), false);
        $response->assertDontSee(route('contracts.index', ['status' => 'attention'], false));
        $response->assertSee('bg-amber-500', false);
        $response->assertDontSee('bg-rose-600', false);
    }

    public function test_sidebar_hides_badge_when_count_zero(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');

        $organization = Organization::factory()->create();
        TenantContext::setOrganizationId($organization->id);
        $user = User::factory()->create(['organization_id' => $organization->id]);

        $this->createContractForOrganization($organization->id, 'Lejos Sidebar', '2026-12-01');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('contract-attention-badge', false);
        $response->assertSee(route('contracts.index'), false);
        $response->assertDontSee(route('contracts.index', ['status' => 'attention'], false));
    }

    public function test_other_plaza_contract_not_counted(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');

        $organization = Organization::factory()->create();
        TenantContext::setOrganizationId($organization->id);
        $user = User::factory()->create(['organization_id' => $organization->id]);

        $plazaA = Plaza::factory()->create(['organization_id' => $organization->id]);
        $plazaB = Plaza::factory()->create(['organization_id' => $organization->id]);

        $this->createContractOnPlaza($organization->id, $plazaB->id, 'Otra Plaza', '2026-08-15');

        TenantContext::setCurrentPlazaId($plazaA->id);
        $this->actingAs($user);

        $summary = ContractAttentionNav::summary();

        $this->assertSame(0, $summary['count']);
        $this->assertFalse($summary['has_expired']);
    }

    private function createContractForOrganization(
        int $organizationId,
        string $tenantName,
        ?string $endsAt = null,
        string $status = Contract::STATUS_ACTIVE,
    ): Contract {
        $property = Property::factory()->create([
            'organization_id' => $organizationId,
        ]);

        $unit = Unit::factory()->create([
            'organization_id' => $organizationId,
            'property_id' => $property->id,
        ]);

        $tenant = Tenant::factory()->create([
            'organization_id' => $organizationId,
            'full_name' => $tenantName,
            'email' => strtolower(str_replace(' ', '.', $tenantName)).'@example.test',
        ]);

        return Contract::factory()->create([
            'organization_id' => $organizationId,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => $status,
            'starts_at' => '2026-01-01',
            'ends_at' => $endsAt,
        ]);
    }

    private function createContractOnPlaza(
        int $organizationId,
        int $plazaId,
        string $tenantName,
        ?string $endsAt = null,
    ): Contract {
        $property = Property::factory()->create([
            'organization_id' => $organizationId,
            'plaza_id' => $plazaId,
        ]);

        $unit = Unit::factory()->create([
            'organization_id' => $organizationId,
            'property_id' => $property->id,
        ]);

        $tenant = Tenant::factory()->create([
            'organization_id' => $organizationId,
            'full_name' => $tenantName,
            'email' => strtolower(str_replace(' ', '.', $tenantName)).'@example.test',
        ]);

        return Contract::factory()->create([
            'organization_id' => $organizationId,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => Contract::STATUS_ACTIVE,
            'starts_at' => '2026-01-01',
            'ends_at' => $endsAt,
        ]);
    }
}
