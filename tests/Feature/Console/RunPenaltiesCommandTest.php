<?php

namespace Tests\Feature\Console;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunPenaltiesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_rent_for_three_days_generates_three_compounded_penalties(): void
    {
        [$contract, $rentCharge] = $this->createOverdueRentContract();

        $this->artisan('inmo:penalties:run', [
            '--date' => '2026-03-04',
            '--from-date' => '2026-03-02',
        ])->assertExitCode(0);

        $penalties = Charge::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $contract->organization_id)
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_PENALTY)
            ->orderBy('penalty_date')
            ->get();

        $this->assertCount(3, $penalties);
        $this->assertSame(
            ['2026-03-02', '2026-03-03', '2026-03-04'],
            $penalties->pluck('penalty_date')->map(fn ($date) => $date?->format('Y-m-d'))->all()
        );
        $this->assertSame(['10.00', '10.10', '10.20'], $penalties->pluck('amount')->all());
    }

    public function test_rate_five_percent_generates_expected_penalties_without_exploding(): void
    {
        [$contract] = $this->createOverdueRentContract(0.05);

        $this->artisan('inmo:penalties:run', [
            '--date' => '2026-03-04',
            '--from-date' => '2026-03-02',
        ])->assertExitCode(0);

        $penalties = Charge::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $contract->organization_id)
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_PENALTY)
            ->orderBy('penalty_date')
            ->pluck('amount')
            ->all();

        $this->assertSame(['50.00', '52.50', '55.13'], $penalties);
    }

    public function test_partial_payment_reduces_penalty_base_starting_next_day(): void
    {
        [$contract, $rentCharge] = $this->createOverdueRentContract();

        $this->artisan('inmo:penalties:run', [
            '--date' => '2026-03-04',
            '--from-date' => '2026-03-04',
        ])->assertExitCode(0);

        $payment = Payment::query()
            ->withoutOrganizationScope()
            ->create([
                'organization_id' => $contract->organization_id,
                'contract_id' => $contract->id,
                'paid_at' => '2026-03-04 12:00:00',
                'amount' => 500,
                'method' => Payment::METHOD_TRANSFER,
                'reference' => 'TRX-500',
                'receipt_folio' => 'REC-TEST-0001',
                'meta' => [
                    'allocation_processed' => true,
                    'credited_amount' => 0,
                ],
            ]);

        PaymentAllocation::query()
            ->withoutOrganizationScope()
            ->create([
                'organization_id' => $contract->organization_id,
                'payment_id' => $payment->id,
                'charge_id' => $rentCharge->id,
                'amount' => 500,
                'meta' => [
                    'source' => 'test',
                ],
            ]);

        $this->artisan('inmo:penalties:run', [
            '--date' => '2026-03-05',
            '--from-date' => '2026-03-05',
        ])->assertExitCode(0);

        $penalties = Charge::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $contract->organization_id)
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_PENALTY)
            ->orderBy('penalty_date')
            ->get(['penalty_date', 'amount', 'meta']);

        $this->assertCount(2, $penalties);

        $baseAtD = (float) data_get($penalties[0]->meta, 'base_amount', 0);
        $baseAtD1 = (float) data_get($penalties[1]->meta, 'base_amount', 0);

        $this->assertGreaterThan(0, $baseAtD);
        $this->assertGreaterThan(0, $baseAtD1);
        $this->assertLessThan($baseAtD, $baseAtD1);
    }

    public function test_command_is_idempotent_for_same_target_date(): void
    {
        [$contract] = $this->createOverdueRentContract();

        $this->artisan('inmo:penalties:run', [
            '--date' => '2026-03-04',
            '--from-date' => '2026-03-02',
        ])->assertExitCode(0);

        $this->artisan('inmo:penalties:run', [
            '--date' => '2026-03-04',
            '--from-date' => '2026-03-02',
        ])->assertExitCode(0);

        $this->assertSame(
            3,
            Charge::query()
                ->withoutOrganizationScope()
                ->where('organization_id', $contract->organization_id)
                ->where('contract_id', $contract->id)
                ->where('type', Charge::TYPE_PENALTY)
                ->count()
        );
    }

    public function test_backfill_generates_missing_days_until_target_date(): void
    {
        [$contract] = $this->createOverdueRentContract();

        $this->artisan('inmo:penalties:run', [
            '--date' => '2026-03-02',
            '--from-date' => '2026-03-02',
        ])->assertExitCode(0);

        $this->artisan('inmo:penalties:run', [
            '--date' => '2026-03-05',
            '--from-date' => '2026-03-03',
        ])->assertExitCode(0);

        $penalties = Charge::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $contract->organization_id)
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_PENALTY)
            ->orderBy('penalty_date')
            ->get(['penalty_date']);

        $this->assertCount(4, $penalties);
        $this->assertSame(
            ['2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05'],
            $penalties->pluck('penalty_date')->map(fn ($date) => $date?->format('Y-m-d'))->all()
        );
    }

    public function test_two_contracts_backfill_six_days_generates_exactly_twelve_penalties(): void
    {
        [$contractA, $contractB] = $this->createTwoOverdueRentContracts();

        $this->artisan('inmo:penalties:run', [
            '--from-date' => '2026-03-05',
            '--date' => '2026-03-10',
        ])->assertExitCode(0);

        $this->assertSame(
            12,
            Charge::query()
                ->withoutOrganizationScope()
                ->where('type', Charge::TYPE_PENALTY)
                ->whereIn('contract_id', [$contractA->id, $contractB->id])
                ->count()
        );
    }

    public function test_range_execution_is_idempotent_when_running_twice(): void
    {
        [$contractA, $contractB] = $this->createTwoOverdueRentContracts();

        $this->artisan('inmo:penalties:run', [
            '--from-date' => '2026-03-05',
            '--date' => '2026-03-10',
        ])->assertExitCode(0);

        $firstCount = Charge::query()
            ->withoutOrganizationScope()
            ->where('type', Charge::TYPE_PENALTY)
            ->whereIn('contract_id', [$contractA->id, $contractB->id])
            ->count();

        $this->artisan('inmo:penalties:run', [
            '--from-date' => '2026-03-05',
            '--date' => '2026-03-10',
        ])->assertExitCode(0);

        $secondCount = Charge::query()
            ->withoutOrganizationScope()
            ->where('type', Charge::TYPE_PENALTY)
            ->whereIn('contract_id', [$contractA->id, $contractB->id])
            ->count();

        $this->assertSame($firstCount, $secondCount);
    }

    /**
     * @return array{0: Contract, 1: Charge}
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

        return [$contract, $rentCharge];
    }

    /**
     * @return array{0: Contract, 1: Contract}
     */
    private function createTwoOverdueRentContracts(): array
    {
        $organization = Organization::factory()->create();

        $property = Property::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $unitA = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $unitB = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);

        $tenantA = Tenant::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $tenantB = Tenant::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $contractA = Contract::factory()->ended()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unitA->id,
            'tenant_id' => $tenantA->id,
            'penalty_rate_daily' => 0.01,
        ]);

        $contractB = Contract::factory()->ended()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unitB->id,
            'tenant_id' => $tenantB->id,
            'penalty_rate_daily' => 0.02,
        ]);

        Charge::query()
            ->withoutOrganizationScope()
            ->create([
                'organization_id' => $organization->id,
                'contract_id' => $contractA->id,
                'unit_id' => $unitA->id,
                'type' => Charge::TYPE_RENT,
                'period' => '2026-03',
                'charge_date' => '2026-03-01',
                'due_date' => '2026-03-01',
                'grace_until' => '2026-03-01',
                'amount' => 1000,
                'meta' => [],
            ]);

        Charge::query()
            ->withoutOrganizationScope()
            ->create([
                'organization_id' => $organization->id,
                'contract_id' => $contractB->id,
                'unit_id' => $unitB->id,
                'type' => Charge::TYPE_RENT,
                'period' => '2026-03',
                'charge_date' => '2026-03-01',
                'due_date' => '2026-03-01',
                'grace_until' => '2026-03-01',
                'amount' => 800,
                'meta' => [],
            ]);

        return [$contractA, $contractB];
    }
}
