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
        $response->assertSeeText('Inquilino Por Vencer');
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
        $response->assertSeeText('Solo Por Vencer');
        $response->assertDontSeeText('Ya Vencido');
        $response->assertDontSeeText('Lejos');
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
        $response->assertSeeText('Atencion Vencido');
        $response->assertSeeText('Atencion Por Vencer');
        $response->assertDontSeeText('Fuera Ventana');
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
