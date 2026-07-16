<?php

namespace Tests\Unit\Actions;

use App\Actions\Charges\GenerateMonthlyRentChargesAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GenerateMonthlyRentChargesActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_it_applies_existing_credit_balance_after_creating_rent_charge(): void
    {
        [$organization, $contract] = $this->makeContractGraph();
        TenantContext::setOrganizationId($organization->id);

        CreditBalance::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'balance' => 500,
        ]);

        $result = app(GenerateMonthlyRentChargesAction::class)
            ->executeForOrganization('2026-03', $organization->id);

        $this->assertSame(1, $result['created']);

        $charge = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_RENT)
            ->where('period', '2026-03')
            ->first();

        $this->assertNotNull($charge);
        $this->assertSame('1000.00', (string) $charge->amount);

        $this->assertDatabaseHas('payment_allocations', [
            'charge_id' => $charge->id,
            'amount' => '500.00',
        ]);

        $this->assertDatabaseHas('payments', [
            'contract_id' => $contract->id,
            'method' => Payment::METHOD_CREDIT,
            'amount' => '500.00',
        ]);

        $this->assertSame(
            0.0,
            (float) CreditBalance::query()->where('contract_id', $contract->id)->value('balance')
        );

        $allocated = (float) \App\Models\PaymentAllocation::query()
            ->where('charge_id', $charge->id)
            ->sum('amount');
        $this->assertSame(500.0, $allocated);
    }

    public function test_it_applies_credit_balance_even_when_rent_charge_already_existed(): void
    {
        [$organization, $contract, $unit] = $this->makeContractGraph();
        TenantContext::setOrganizationId($organization->id);

        $periodStart = \Carbon\CarbonImmutable::createFromFormat('Y-m', '2026-03')->startOfMonth();
        $dueDate = $periodStart->day(min(max((int) $contract->due_day, 1), $periodStart->daysInMonth));

        $charge = Charge::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_RENT,
            'period' => '2026-03',
            'charge_date' => $periodStart->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'grace_until' => $dueDate->addDays($contract->grace_days)->toDateString(),
            'amount' => 1000,
            'meta' => [],
        ]);

        CreditBalance::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'balance' => 200,
        ]);

        $result = app(GenerateMonthlyRentChargesAction::class)
            ->executeForOrganization('2026-03', $organization->id);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['skipped']);

        $this->assertDatabaseHas('payment_allocations', [
            'charge_id' => $charge->id,
            'amount' => '200.00',
        ]);

        $this->assertSame(
            0.0,
            (float) CreditBalance::query()->where('contract_id', $contract->id)->value('balance')
        );
    }

    public function test_it_skips_gracefully_when_rent_charge_is_created_concurrently(): void
    {
        [$organization, $contract] = $this->makeContractGraph();
        TenantContext::setOrganizationId($organization->id);

        $now = now();

        Charge::creating(function (Charge $charge) use ($contract, $now): void {
            if ($charge->type !== Charge::TYPE_RENT || $charge->period !== '2026-03') {
                return;
            }

            // Simulate a concurrent process winning the race and inserting the
            // same RENT charge right before this process's insert executes.
            DB::table('charges')->insert([
                'organization_id' => $contract->organization_id,
                'contract_id' => $contract->id,
                'unit_id' => $contract->unit_id,
                'type' => Charge::TYPE_RENT,
                'period' => '2026-03',
                'rent_period_key' => '2026-03',
                'charge_date' => '2026-03-01',
                'due_date' => '2026-03-01',
                'grace_until' => '2026-03-06',
                'amount' => 1000,
                'meta' => json_encode([]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        $result = app(GenerateMonthlyRentChargesAction::class)
            ->executeForOrganization('2026-03', $organization->id);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['skipped']);

        $count = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_RENT)
            ->where('period', '2026-03')
            ->count();

        $this->assertSame(1, $count);
    }

    /**
     * @return array{0: Organization, 1: Contract, 2: Unit}
     */
    private function makeContractGraph(): array
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
        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => Contract::STATUS_ACTIVE,
            'rent_amount' => 1000,
            'due_day' => 1,
            'grace_days' => 5,
            'starts_at' => '2026-01-01',
            'ends_at' => null,
        ]);

        return [$organization, $contract, $unit];
    }
}
