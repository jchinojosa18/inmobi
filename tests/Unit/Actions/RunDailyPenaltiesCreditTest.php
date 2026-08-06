<?php

namespace Tests\Unit\Actions;

use App\Actions\Payments\ApplyCreditBalanceAction;
use App\Actions\Payments\ApplyPaymentAction;
use App\Actions\Penalties\RunDailyPenaltiesAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAllocation;
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

    public function test_penalty_base_excludes_credit_already_consumed_via_method_credit(): void
    {
        [$organization, $contract, $rentCharge] = $this->createOverdueRentContract(penaltyRateDaily: 0.10);
        TenantContext::setOrganizationId($organization->id);

        // Overpay: allocates 1000 to rent and leaves 500 credit (meta.credited_amount stays 500 forever).
        $overpayment = Payment::query()->withoutOrganizationScope()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'amount' => 1500,
            'method' => Payment::METHOD_CASH,
            'paid_at' => '2026-03-01 12:00:00',
            'meta' => [],
        ]);
        app(ApplyPaymentAction::class)->execute($contract, $overpayment);

        $this->assertSame(
            500.0,
            (float) CreditBalance::query()->withoutOrganizationScope()->where('contract_id', $contract->id)->value('balance')
        );

        // New overdue rent absorbs the credit via METHOD_CREDIT.
        $secondRent = Charge::query()->withoutOrganizationScope()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_RENT,
            'period' => '2026-04',
            'rent_period_key' => '2026-04',
            'charge_date' => '2026-03-02',
            'due_date' => '2026-03-02',
            'grace_until' => '2026-03-02',
            'amount' => 1000,
            'meta' => [],
        ]);

        app(ApplyCreditBalanceAction::class)->execute($contract->fresh());

        $this->assertSame(
            0.0,
            (float) CreditBalance::query()->withoutOrganizationScope()->where('contract_id', $contract->id)->value('balance')
        );
        $this->assertSame(
            500.0,
            (float) PaymentAllocation::query()->withoutOrganizationScope()->where('charge_id', $secondRent->id)->sum('amount')
        );

        // Penalty for 2026-03-04 uses cutoff 2026-03-03 23:59:59 — pending should be 500, not 0.
        $result = app(RunDailyPenaltiesAction::class)->execute(
            CarbonImmutable::createFromFormat('Y-m-d', '2026-03-04', 'America/Tijuana'),
            CarbonImmutable::createFromFormat('Y-m-d', '2026-03-04', 'America/Tijuana'),
            (int) $contract->id,
        );

        $this->assertSame(1, $result['created']);

        $penalty = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_PENALTY)
            ->whereDate('penalty_date', '2026-03-04')
            ->first();

        $this->assertNotNull($penalty);
        $this->assertSame('50.00', (string) $penalty->amount);
        $this->assertSame(500.0, (float) data_get($penalty->meta, 'base_amount'));
        $this->assertSame(1000.0, (float) $rentCharge->fresh()->amount);
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
