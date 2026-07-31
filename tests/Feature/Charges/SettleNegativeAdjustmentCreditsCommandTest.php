<?php

namespace Tests\Feature\Charges;

use App\Actions\MonthCloses\CloseMonthAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettleNegativeAdjustmentCreditsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_backfill_settles_negative_adjustment_and_is_idempotent(): void
    {
        $organization = Organization::factory()->create();
        TenantContext::setOrganizationId($organization->id);
        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $tenant = Tenant::factory()->create(['organization_id' => $organization->id]);
        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => Contract::STATUS_ENDED,
            'ends_at' => '2026-12-31',
            'rent_amount' => 0,
        ]);

        CreditBalance::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'balance' => 198.75,
        ]);

        $charge = Charge::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_ADJUSTMENT,
            'period' => '2026-07',
            'charge_date' => '2026-07-31',
            'amount' => -100.0,
            'meta' => ['reason' => 'legacy discount'],
        ]);

        TenantContext::clear();
        $this->assertNull(TenantContext::currentOrganizationId());

        $this->artisan('inmo:adjustments:settle-negative-credits', [
            '--contract-id' => $contract->id,
        ])->assertSuccessful();

        $charge = Charge::query()->withoutOrganizationScope()->findOrFail($charge->id);
        $this->assertTrue((bool) data_get($charge->meta, 'settled_as_credit'));
        $this->assertSame(
            298.75,
            (float) CreditBalance::query()
                ->withoutOrganizationScope()
                ->where('contract_id', $contract->id)
                ->value('balance')
        );

        $this->assertNull(TenantContext::currentOrganizationId());

        $this->artisan('inmo:adjustments:settle-negative-credits', [
            '--contract-id' => $contract->id,
        ])->assertSuccessful();

        $this->assertSame(
            298.75,
            (float) CreditBalance::query()
                ->withoutOrganizationScope()
                ->where('contract_id', $contract->id)
                ->value('balance')
        );
    }

    public function test_backfill_settles_negative_adjustment_in_closed_month(): void
    {
        $organization = Organization::factory()->create();
        TenantContext::setOrganizationId($organization->id);
        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $tenant = Tenant::factory()->create(['organization_id' => $organization->id]);
        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => Contract::STATUS_ENDED,
            'ends_at' => '2026-12-31',
            'rent_amount' => 0,
        ]);

        $charge = Charge::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_ADJUSTMENT,
            'period' => '2026-03',
            'charge_date' => '2026-03-20',
            'amount' => -75.5,
            'meta' => ['reason' => 'legacy discount'],
        ]);

        $user = User::factory()->create(['organization_id' => $organization->id]);

        app(CloseMonthAction::class)->execute(
            organizationId: (int) $organization->id,
            userId: (int) $user->id,
            month: '2026-03',
        );

        TenantContext::clear();

        $this->artisan('inmo:adjustments:settle-negative-credits', [
            '--contract-id' => $contract->id,
        ])->assertSuccessful();

        $charge = Charge::query()->withoutOrganizationScope()->findOrFail($charge->id);
        $this->assertTrue((bool) data_get($charge->meta, 'settled_as_credit'));
        $this->assertSame(75.5, (float) data_get($charge->meta, 'credit_amount'));

        $this->assertSame(
            75.5,
            (float) CreditBalance::query()
                ->withoutOrganizationScope()
                ->where('contract_id', $contract->id)
                ->value('balance')
        );
    }
}
