<?php

namespace Tests\Unit\Actions;

use App\Actions\Penalties\RunDailyPenaltiesAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunDailyPenaltiesCreditTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_it_applies_credit_to_overdue_rent_before_the_new_penalty(): void
    {
        [$organization, $contract, $rentCharge] = $this->createOverdueRentContract();

        CreditBalance::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'balance' => 50,
        ]);

        $result = app(RunDailyPenaltiesAction::class)->execute(
            CarbonImmutable::createFromFormat('Y-m-d', '2026-03-02', 'America/Tijuana'),
            CarbonImmutable::createFromFormat('Y-m-d', '2026-03-02', 'America/Tijuana'),
        );

        $this->assertSame(1, $result['created']);

        $penalty = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_PENALTY)
            ->first();

        $this->assertNotNull($penalty);
        $this->assertSame('10.00', (string) $penalty->amount);

        $this->assertDatabaseHas('payment_allocations', [
            'charge_id' => $rentCharge->id,
            'amount' => '50.00',
        ]);

        $this->assertDatabaseMissing('payment_allocations', [
            'charge_id' => $penalty->id,
        ]);

        $this->assertDatabaseHas('payments', [
            'contract_id' => $contract->id,
            'method' => Payment::METHOD_CREDIT,
            'amount' => '50.00',
        ]);

        $this->assertSame(
            0.0,
            (float) CreditBalance::query()->withoutOrganizationScope()->where('contract_id', $contract->id)->value('balance')
        );
    }

    /**
     * @return array{0: Organization, 1: Contract, 2: Charge}
     */
    private function createOverdueRentContract(float $penaltyRateDaily = 0.01): array
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

        $contract = Contract::factory()->ended()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'penalty_rate_daily' => $penaltyRateDaily,
        ]);

        $rentCharge = Charge::query()
            ->withoutOrganizationScope()
            ->create([
                'organization_id' => $organization->id,
                'contract_id' => $contract->id,
                'unit_id' => $unit->id,
                'type' => Charge::TYPE_RENT,
                'period' => '2026-03',
                'charge_date' => '2026-03-01',
                'due_date' => '2026-03-01',
                'grace_until' => '2026-03-01',
                'amount' => 1000,
                'meta' => [],
            ]);

        return [$organization, $contract, $rentCharge];
    }
}
