<?php

namespace Tests\Feature\Contracts;

use App\Models\Contract;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\DateDisplay;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractExpiringSoonTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_index_shows_expiring_badge_and_expiration_column(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');
        [$user] = $this->createOrganizationWithUser();

        $this->createContractForOrganization(
            $user->organization_id,
            tenantName: 'Inquilino Por Vencer',
            endsAt: '2026-08-15',
        );

        $response = $this->actingAs($user)->get(route('contracts.index', ['status' => 'all']));

        $response->assertOk();
        $response->assertSeeText('INQUILINO POR VENCER');
        $response->assertSeeText(__('contracts.status_expiring_label'));
        $response->assertSeeText(DateDisplay::formatDate('2026-08-15'));
        $response->assertSeeText(__('contracts.ends_in_days', ['days' => 14]));
    }

    public function test_expiring_filter_shows_only_window(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');
        [$user] = $this->createOrganizationWithUser();

        $this->createContractForOrganization($user->organization_id, 'Solo Por Vencer', '2026-08-20');
        $this->createContractForOrganization($user->organization_id, 'Ya Vencido', '2026-07-20');
        $this->createContractForOrganization($user->organization_id, 'Lejos', '2026-12-01');

        $response = $this->actingAs($user)->get(route('contracts.index', ['status' => 'expiring']));

        $response->assertOk();
        $response->assertSeeText('SOLO POR VENCER');
        // Expired still appears in the unfiltered attention section above.
        $response->assertSeeText('YA VENCIDO');
        $response->assertDontSeeText('LEJOS');
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'YA VENCIDO'));
        $this->assertGreaterThanOrEqual(1, substr_count($html, 'SOLO POR VENCER'));
    }

    public function test_attention_filter_includes_expired_and_expiring(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');
        [$user] = $this->createOrganizationWithUser();

        $this->createContractForOrganization($user->organization_id, 'Atencion Vencido', '2026-07-20');
        $this->createContractForOrganization($user->organization_id, 'Atencion Por Vencer', '2026-08-20');
        $this->createContractForOrganization($user->organization_id, 'Fuera Ventana', '2026-12-01');

        $response = $this->actingAs($user)->get(route('contracts.index', ['status' => 'attention']));

        $response->assertOk();
        $response->assertSeeText('ATENCION VENCIDO');
        $response->assertSeeText('ATENCION POR VENCER');
        $response->assertDontSeeText('FUERA VENTANA');
    }

    public function test_attention_section_always_shows_and_ignores_main_filters(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');
        [$user] = $this->createOrganizationWithUser();

        $this->createContractForOrganization($user->organization_id, 'Seccion Atencion', '2026-08-20');
        $this->createContractForOrganization($user->organization_id, 'Activo Lejos', '2026-12-01');

        $response = $this->actingAs($user)->get(route('contracts.index', [
            'status' => 'ended',
            'q' => 'NoDeberiaFiltrarAtencion',
        ]));

        $response->assertOk();
        $response->assertSeeText(__('contracts.status_attention'));
        $response->assertSeeText('SECCION ATENCION');
        $response->assertDontSeeText('ACTIVO LEJOS');
    }

    public function test_active_list_includes_attention_contracts(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');
        [$user] = $this->createOrganizationWithUser();

        $this->createContractForOrganization($user->organization_id, 'Duplicado Atencion', '2026-08-10');
        $this->createContractForOrganization($user->organization_id, 'Activo Normal', '2026-12-01');

        $response = $this->actingAs($user)->get(route('contracts.index'));

        $response->assertOk();
        $response->assertSeeText(__('contracts.status_attention'));
        $response->assertSeeText('DUPLICADO ATENCION');
        $response->assertSeeText('ACTIVO NORMAL');
        $html = $response->getContent();
        $this->assertSame(2, substr_count($html, 'DUPLICADO ATENCION'));
    }

    public function test_show_displays_expiring_banner(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');
        [$user] = $this->createOrganizationWithUser();

        $contract = $this->createContractForOrganization(
            $user->organization_id,
            'Banner Por Vencer',
            '2026-08-20',
        );

        $response = $this->actingAs($user)->get(route('contracts.show', $contract));

        $response->assertOk();
        $response->assertSeeText(__('contracts.expiring_banner', [
            'date' => DateDisplay::formatDate('2026-08-20'),
        ]));
        $response->assertSeeText(__('contracts.status_expiring_label'));
    }

    /**
     * @return array{User}
     */
    private function createOrganizationWithUser(): array
    {
        $organization = Organization::factory()->create();
        TenantContext::setOrganizationId($organization->id);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        return [$user];
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
}
