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

class ContractExpiredBadgeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_is_expired_is_true_for_active_contract_past_ends_at(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');

        $contract = Contract::factory()->make([
            'status' => Contract::STATUS_ACTIVE,
            'ends_at' => '2026-07-31',
        ]);

        $this->assertTrue($contract->isExpired());
    }

    public function test_is_expired_is_false_when_ends_at_is_today(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');

        $contract = Contract::factory()->make([
            'status' => Contract::STATUS_ACTIVE,
            'ends_at' => '2026-08-01',
        ]);

        $this->assertFalse($contract->isExpired());
    }

    public function test_is_expired_is_false_for_ended_contract(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');

        $contract = Contract::factory()->make([
            'status' => Contract::STATUS_ENDED,
            'ends_at' => '2026-07-31',
        ]);

        $this->assertFalse($contract->isExpired());
    }

    public function test_index_shows_vencido_badge_for_expired_active_contract(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');

        [$user] = $this->createOrganizationWithUser();

        $this->createContractForOrganization(
            $user->organization_id,
            tenantName: 'Inquilino Vencido Badge',
            endsAt: '2026-07-31',
        );

        $this->createContractForOrganization(
            $user->organization_id,
            tenantName: 'Inquilino Activo Badge',
            endsAt: '2026-12-31',
        );

        $response = $this->actingAs($user)->get(route('contracts.index', [
            'status' => 'all',
        ]));

        $response->assertOk();
        $response->assertSeeText('INQUILINO VENCIDO BADGE');
        $response->assertSeeText(__('contracts.status_expired_label'));
        $response->assertSeeText('INQUILINO ACTIVO BADGE');
        $response->assertSeeText(__('common.active'));
    }

    public function test_expired_status_filter_shows_only_expired_active_contracts(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');

        [$user] = $this->createOrganizationWithUser();

        $this->createContractForOrganization(
            $user->organization_id,
            tenantName: 'Solo Expirado Filtro',
            endsAt: '2026-07-31',
        );

        $this->createContractForOrganization(
            $user->organization_id,
            tenantName: 'Activo Sin Expirar',
            endsAt: '2026-12-31',
        );

        $this->createContractForOrganization(
            $user->organization_id,
            tenantName: 'Finalizado No Expirado',
            endsAt: '2026-07-31',
            status: Contract::STATUS_ENDED,
        );

        $response = $this->actingAs($user)->get(route('contracts.index', [
            'status' => 'expired',
        ]));

        $response->assertOk();
        $response->assertSeeText('SOLO EXPIRADO FILTRO');
        $response->assertDontSeeText('ACTIVO SIN EXPIRAR');
        $response->assertDontSeeText('FINALIZADO NO EXPIRADO');
    }

    public function test_ended_contract_does_not_show_expiration_days_subtitle(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');

        [$user] = $this->createOrganizationWithUser();

        $this->createContractForOrganization(
            $user->organization_id,
            tenantName: 'Contrato Finalizado Subtitulo',
            endsAt: '2026-07-31',
            status: Contract::STATUS_ENDED,
        );

        $response = $this->actingAs($user)->get(route('contracts.index', [
            'status' => 'all',
        ]));

        $response->assertOk();
        $response->assertSeeText('CONTRATO FINALIZADO SUBTITULO');
        $response->assertSeeText(DateDisplay::formatDate('2026-07-31'));
        $response->assertSeeText(__('common.finished'));
        $response->assertDontSeeText(__('contracts.ended_days_ago', ['days' => 1]));
    }

    public function test_show_displays_expired_banner(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');

        [$user] = $this->createOrganizationWithUser();

        $contract = $this->createContractForOrganization(
            $user->organization_id,
            tenantName: 'Banner Vencido Show',
            endsAt: '2026-07-31',
        );

        $response = $this->actingAs($user)->get(route('contracts.show', $contract));

        $response->assertOk();
        $response->assertSeeText(__('contracts.expired_banner', [
            'date' => DateDisplay::formatDate('2026-07-31'),
        ]));
        $response->assertSeeText(__('contracts.status_expired_label'));
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
