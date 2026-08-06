<?php

namespace Tests\Unit\Models;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChargeStatusAppendTest extends TestCase
{
    use RefreshDatabase;

    public function test_serializing_charges_does_not_n_plus_one_on_status(): void
    {
        [$organization, $contract, $unit] = $this->makeContractGraph();

        foreach ([1 => 100, 2 => 200, 3 => 300] as $month => $amount) {
            $period = sprintf('2026-%02d', $month);
            $charge = Charge::factory()->create([
                'organization_id' => $organization->id,
                'contract_id' => $contract->id,
                'unit_id' => $unit->id,
                'type' => Charge::TYPE_OTHER,
                'period' => $period,
                'amount' => $amount,
                'charge_date' => $period.'-01',
            ]);

            $payment = Payment::factory()->create([
                'organization_id' => $organization->id,
                'contract_id' => $contract->id,
                'amount' => $amount / 2,
            ]);

            PaymentAllocation::factory()->create([
                'organization_id' => $organization->id,
                'payment_id' => $payment->id,
                'charge_id' => $charge->id,
                'amount' => $amount / 2,
            ]);
        }

        $charges = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->get();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $payload = $charges->toArray();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $allocationQueries = collect($queries)->filter(function (array $query): bool {
            return str_contains(strtolower($query['query']), 'payment_allocations');
        });

        $this->assertCount(0, $allocationQueries, 'status must not be appended (would N+1 sum per charge)');
        $this->assertArrayNotHasKey('status', $payload[0]);
    }

    public function test_status_accessor_still_works_when_read_explicitly(): void
    {
        [$organization, $contract, $unit] = $this->makeContractGraph();

        $charge = Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_OTHER,
            'period' => '2026-03',
            'amount' => 1000,
            'charge_date' => '2026-03-01',
        ]);

        $payment = Payment::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'amount' => 400,
        ]);

        PaymentAllocation::factory()->create([
            'organization_id' => $organization->id,
            'payment_id' => $payment->id,
            'charge_id' => $charge->id,
            'amount' => 400,
        ]);

        $this->assertSame(Charge::STATUS_PARTIAL, $charge->fresh()->status);
    }

    /**
     * @return array{0: Organization, 1: Contract, 2: Unit}
     */
    private function makeContractGraph(): array
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
            'rent_amount' => 0,
        ]);

        return [$organization, $contract, $unit];
    }
}
