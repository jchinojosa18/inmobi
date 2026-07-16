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

class RebuildPenaltiesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rebuilds_penalties_for_contract_in_date_range(): void
    {
        [$contract] = $this->createOverdueRentContract();

        $this->artisan('inmo:penalties:run', [
            '--date' => '2026-03-04',
            '--from-date' => '2026-03-02',
        ])->assertExitCode(0);

        $this->assertSame(
            3,
            Charge::query()
                ->withoutOrganizationScope()
                ->where('contract_id', $contract->id)
                ->where('type', Charge::TYPE_PENALTY)
                ->count()
        );

        $this->artisan('inmo:penalties:rebuild', [
            '--contract' => $contract->id,
            '--from' => '2026-03-02',
            '--to' => '2026-03-04',
        ])->assertExitCode(0)
            ->expectsOutputToContain('Penalties borradas: 3')
            ->expectsOutputToContain('Penalties creadas: 3');

        $penaltyDates = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_PENALTY)
            ->orderBy('penalty_date')
            ->pluck('penalty_date')
            ->map(fn ($date) => $date?->format('Y-m-d'))
            ->all();

        $this->assertSame(['2026-03-02', '2026-03-03', '2026-03-04'], $penaltyDates);
    }

    public function test_it_aborts_when_penalties_have_payment_allocations(): void
    {
        [$contract] = $this->createOverdueRentContract();

        $this->artisan('inmo:penalties:run', [
            '--date' => '2026-03-04',
            '--from-date' => '2026-03-02',
        ])->assertExitCode(0);

        $penalty = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_PENALTY)
            ->orderBy('penalty_date')
            ->first();

        $payment = Payment::query()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'paid_at' => '2026-03-04 10:00:00',
            'amount' => 10,
            'method' => 'CASH',
            'receipt_folio' => 'REC-2026-000001',
            'meta' => [],
        ]);

        PaymentAllocation::query()->create([
            'organization_id' => $contract->organization_id,
            'payment_id' => $payment->id,
            'charge_id' => $penalty->id,
            'amount' => 10,
            'meta' => [],
        ]);

        $this->artisan('inmo:penalties:rebuild', [
            '--contract' => $contract->id,
            '--from' => '2026-03-02',
            '--to' => '2026-03-04',
        ])->assertExitCode(1)
            ->expectsOutputToContain('Cannot rebuild: penalties have payment allocations.');

        $this->assertSame(
            3,
            Charge::query()
                ->withoutOrganizationScope()
                ->where('contract_id', $contract->id)
                ->where('type', Charge::TYPE_PENALTY)
                ->count()
        );
    }

    public function test_it_never_hard_deletes_charge_rows_during_rebuild(): void
    {
        [$contract] = $this->createOverdueRentContract();

        $this->artisan('inmo:penalties:run', [
            '--date' => '2026-03-04',
            '--from-date' => '2026-03-02',
        ])->assertExitCode(0);

        $penaltyIds = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_PENALTY)
            ->pluck('id');

        $this->artisan('inmo:penalties:rebuild', [
            '--contract' => $contract->id,
            '--from' => '2026-03-02',
            '--to' => '2026-03-04',
        ])->assertExitCode(0);

        // Original rows must still physically exist (soft-deleted and/or restored),
        // proving the command never issued a raw DB::table hard delete.
        foreach ($penaltyIds as $penaltyId) {
            $this->assertDatabaseHas('charges', ['id' => $penaltyId]);
        }
    }

    /**
     * @return array{0: Contract, 1: Charge}
     */
    private function createOverdueRentContract(): array
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
            'penalty_rate_daily' => 0.01,
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
}
