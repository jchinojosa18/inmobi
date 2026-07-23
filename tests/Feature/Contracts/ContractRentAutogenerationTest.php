<?php

namespace Tests\Feature\Contracts;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\MonthClose;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractRentAutogenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_contract_creation_generates_current_month_rent_charge(): void
    {
        CarbonImmutable::setTestNow('2026-03-15 09:00:00');

        [$organization, $unit, $tenant] = $this->createContractGraph();

        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => Contract::STATUS_ACTIVE,
            'rent_amount' => 12500,
            'due_day' => 8,
            'grace_days' => 5,
        ]);

        $charge = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_RENT)
            ->where('period', '2026-03')
            ->first();

        $this->assertNotNull($charge);
        $this->assertSame('2026-03-01', $charge->charge_date?->format('Y-m-d'));
        $this->assertSame('2026-03-08', $charge->due_date?->format('Y-m-d'));
        $this->assertSame('2026-03-13', $charge->grace_until?->format('Y-m-d'));
        $this->assertSame('12500.00', $charge->amount);

        CarbonImmutable::setTestNow();
    }

    public function test_contract_activation_generates_current_month_rent_charge_once(): void
    {
        CarbonImmutable::setTestNow('2026-03-15 09:00:00');

        [$organization, $unit, $tenant] = $this->createContractGraph();

        $contract = Contract::factory()->ended()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'rent_amount' => 8000,
            'due_day' => 5,
            'grace_days' => 2,
        ]);

        $this->assertSame(
            0,
            Charge::query()
                ->withoutOrganizationScope()
                ->where('contract_id', $contract->id)
                ->where('type', Charge::TYPE_RENT)
                ->where('period', '2026-03')
                ->count()
        );

        $contract->update([
            'status' => Contract::STATUS_ACTIVE,
            'ends_at' => null,
        ]);

        $contract->update([
            'status' => Contract::STATUS_ACTIVE,
        ]);

        $this->assertSame(
            1,
            Charge::query()
                ->withoutOrganizationScope()
                ->where('contract_id', $contract->id)
                ->where('type', Charge::TYPE_RENT)
                ->where('period', '2026-03')
                ->count()
        );

        CarbonImmutable::setTestNow();
    }

    public function test_updating_due_day_syncs_open_month_rent_charge_schedule(): void
    {
        CarbonImmutable::setTestNow('2026-03-15 09:00:00');

        [$organization, $unit, $tenant] = $this->createContractGraph();

        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => Contract::STATUS_ACTIVE,
            'rent_amount' => 10000,
            'due_day' => 8,
            'grace_days' => 5,
        ]);

        $charge = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_RENT)
            ->where('period', '2026-03')
            ->first();

        $this->assertNotNull($charge);
        $this->assertSame('2026-03-08', $charge->due_date?->format('Y-m-d'));
        $this->assertSame('2026-03-13', $charge->grace_until?->format('Y-m-d'));

        $contract->update([
            'due_day' => 20,
            'grace_days' => 3,
        ]);

        $charge->refresh();

        $this->assertSame('2026-03-20', $charge->due_date?->format('Y-m-d'));
        $this->assertSame('2026-03-23', $charge->grace_until?->format('Y-m-d'));

        CarbonImmutable::setTestNow();
    }

    public function test_updating_due_day_does_not_touch_closed_month_rent_charge(): void
    {
        CarbonImmutable::setTestNow('2026-03-15 09:00:00');

        [$organization, $unit, $tenant] = $this->createContractGraph();

        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => Contract::STATUS_ACTIVE,
            'rent_amount' => 10000,
            'due_day' => 8,
            'grace_days' => 5,
            'starts_at' => '2026-02-01',
        ]);

        $februaryCharge = Charge::query()->withoutOrganizationScope()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_RENT,
            'period' => '2026-02',
            'charge_date' => '2026-02-01',
            'due_date' => '2026-02-08',
            'grace_until' => '2026-02-13',
            'amount' => 10000,
            'meta' => [],
        ]);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        MonthClose::query()->withoutOrganizationScope()->create([
            'organization_id' => $organization->id,
            'month' => '2026-02',
            'closed_at' => now(),
            'closed_by_user_id' => $user->id,
            'snapshot' => [
                'ingresos_operativos' => 0,
                'egresos' => 0,
                'neto' => 0,
                'cartera' => 0,
                'conteos' => [
                    'contratos_activos' => 0,
                    'pagos' => 0,
                    'egresos' => 0,
                ],
            ],
        ]);

        $contract->update([
            'due_day' => 20,
            'grace_days' => 3,
        ]);

        $februaryCharge->refresh();
        $marchCharge = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_RENT)
            ->where('period', '2026-03')
            ->first();

        $this->assertSame('2026-02-08', $februaryCharge->due_date?->format('Y-m-d'));
        $this->assertSame('2026-02-13', $februaryCharge->grace_until?->format('Y-m-d'));
        $this->assertNotNull($marchCharge);
        $this->assertSame('2026-03-20', $marchCharge->due_date?->format('Y-m-d'));
        $this->assertSame('2026-03-23', $marchCharge->grace_until?->format('Y-m-d'));

        CarbonImmutable::setTestNow();
    }

    /**
     * @return array{Organization, Unit, Tenant}
     */
    private function createContractGraph(): array
    {
        $organization = Organization::factory()->create();
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $tenant = Tenant::factory()->create([
            'organization_id' => $organization->id,
        ]);

        return [$organization, $unit, $tenant];
    }
}
