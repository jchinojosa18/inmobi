<?php

namespace Tests\Unit\Actions;

use App\Actions\Charges\RegisterContractAdjustmentAction;
use App\Actions\MonthCloses\BuildMonthCloseSnapshotAction;
use App\Actions\Payments\ApplyCreditBalanceAction;
use App\Actions\Payments\ApplyPaymentAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildMonthCloseSnapshotCarteraTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_cartera_does_not_double_count_settled_negative_adjustment(): void
    {
        [$organization, $contract] = $this->makeContractWithRent(1000.0);
        TenantContext::setOrganizationId($organization->id);
        $user = User::factory()->create(['organization_id' => $organization->id]);

        CreditBalance::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'contract_id' => $contract->id,
            ],
            ['balance' => 0]
        );

        app(RegisterContractAdjustmentAction::class)->execute(
            contract: $contract,
            amount: -200.0,
            chargeDate: CarbonImmutable::parse('2026-03-10'),
            reason: 'Condonación parcial',
            createdByUserId: (int) $user->id,
        );

        $snapshot = app(BuildMonthCloseSnapshotAction::class)->execute($organization->id, '2026-03');

        $this->assertSame(800.0, (float) $snapshot['cartera']);
    }

    public function test_cartera_excludes_credit_already_consumed_via_method_credit(): void
    {
        [$organization, $contract] = $this->makeContractWithRent(1000.0);
        TenantContext::setOrganizationId($organization->id);

        $overpayment = Payment::query()->withoutOrganizationScope()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'amount' => 1500,
            'method' => Payment::METHOD_CASH,
            'paid_at' => '2026-03-05 12:00:00',
            'meta' => [],
        ]);
        app(ApplyPaymentAction::class)->execute($contract, $overpayment);

        Charge::query()->withoutOrganizationScope()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_RENT,
            'period' => '2026-04',
            'rent_period_key' => '2026-04',
            'charge_date' => '2026-03-15',
            'due_date' => '2026-03-15',
            'amount' => 1000,
            'meta' => [],
        ]);

        app(ApplyCreditBalanceAction::class)->execute($contract->fresh());

        $snapshot = app(BuildMonthCloseSnapshotAction::class)->execute($organization->id, '2026-03');

        // First rent paid; second rent 1000 − 500 credit applied = 500 cartera.
        $this->assertSame(500.0, (float) $snapshot['cartera']);
    }

    public function test_cartera_matches_ledger_outstanding_calculator_on_same_fixture(): void
    {
        [$organization, $contract] = $this->makeContractWithRent(1000.0);
        TenantContext::setOrganizationId($organization->id);
        $user = User::factory()->create(['organization_id' => $organization->id]);

        CreditBalance::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'contract_id' => $contract->id,
            ],
            ['balance' => 0]
        );

        app(RegisterContractAdjustmentAction::class)->execute(
            contract: $contract,
            amount: -200.0,
            chargeDate: CarbonImmutable::parse('2026-03-10'),
            reason: 'Condonación parcial',
            createdByUserId: (int) $user->id,
        );

        $overpayment = Payment::query()->withoutOrganizationScope()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'amount' => 300,
            'method' => Payment::METHOD_CASH,
            'paid_at' => '2026-03-12 12:00:00',
            'meta' => [],
        ]);
        app(ApplyPaymentAction::class)->execute($contract->fresh(), $overpayment);

        $snapshot = app(BuildMonthCloseSnapshotAction::class)->execute($organization->id, '2026-03');

        $periodStart = CarbonImmutable::createFromFormat('!Y-m', '2026-03', 'America/Tijuana')->startOfMonth();
        $periodEnd = $periodStart->endOfMonth();
        $cutoffTimestamp = $periodEnd->setTime(23, 59, 59);

        $live = app(\App\Support\LedgerOutstandingCalculator::class)->outstandingForOrganizationAsOf(
            organizationId: (int) $organization->id,
            chargeDateTo: $periodEnd->toDateString(),
            paymentPaidAtTo: $cutoffTimestamp->toDateTimeString(),
        );

        $this->assertSame($live, (float) $snapshot['cartera']);
        // RENT 1000 − ADJ credit 200 − payment 300 = 500.
        $this->assertSame(500.0, (float) $snapshot['cartera']);
    }

    /**
     * @return array{0: Organization, 1: Contract}
     */
    private function makeContractWithRent(float $rentAmount): array
    {
        $organization = Organization::factory()->create();
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
            'rent_amount' => $rentAmount,
        ]);

        $rents = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_RENT)
            ->orderBy('id')
            ->get();

        $rent = $rents->first();
        if ($rent === null) {
            Charge::query()->withoutOrganizationScope()->create([
                'organization_id' => $organization->id,
                'contract_id' => $contract->id,
                'unit_id' => $unit->id,
                'type' => Charge::TYPE_RENT,
                'period' => '2026-03',
                'rent_period_key' => '2026-03',
                'charge_date' => '2026-03-01',
                'due_date' => '2026-03-01',
                'amount' => $rentAmount,
                'meta' => [],
            ]);
        } else {
            $rent->update([
                'amount' => $rentAmount,
                'period' => '2026-03',
                'rent_period_key' => '2026-03',
                'charge_date' => '2026-03-01',
                'due_date' => '2026-03-01',
            ]);
            $rents->skip(1)->each(fn (Charge $extra) => $extra->forceDelete());
        }

        return [$organization, $contract->fresh()];
    }
}
