<?php

namespace Tests\Unit\Support;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\OperatingIncomeService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatingIncomeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_totals_by_type_match_sum_of_allocation_details(): void
    {
        $organization = Organization::factory()->create();
        User::factory()->create(['organization_id' => $organization->id]);

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
            'status' => Contract::STATUS_ACTIVE,
        ]);

        $rent = Charge::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_RENT,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 1000,
            'meta' => [],
        ]);
        $penalty = Charge::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_PENALTY,
            'period' => '2026-03',
            'charge_date' => '2026-03-07',
            'amount' => 120,
            'meta' => [],
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'paid_at' => '2026-03-10 10:00:00',
            'amount' => 1120,
            'method' => Payment::METHOD_TRANSFER,
            'reference' => 'P-JOIN',
            'receipt_folio' => 'REC-JOIN-001',
            'meta' => [],
        ]);

        PaymentAllocation::query()->create([
            'organization_id' => $organization->id,
            'payment_id' => $payment->id,
            'charge_id' => $rent->id,
            'amount' => 1000,
            'meta' => [],
        ]);
        PaymentAllocation::query()->create([
            'organization_id' => $organization->id,
            'payment_id' => $payment->id,
            'charge_id' => $penalty->id,
            'amount' => 120,
            'meta' => [],
        ]);

        $from = CarbonImmutable::parse('2026-03-01', 'America/Tijuana')->startOfDay();
        $to = CarbonImmutable::parse('2026-03-31', 'America/Tijuana')->endOfDay();
        $service = app(OperatingIncomeService::class);

        $detailsSum = round((float) $service->allocationsForRange(
            (int) $organization->id,
            $from,
            $to,
        )->sum('allocated_amount'), 2);
        $typesSum = round((float) array_sum($service->totalsByTypeForRange(
            (int) $organization->id,
            $from,
            $to,
        )), 2);

        $this->assertSame(1120.0, $detailsSum);
        $this->assertSame($detailsSum, $typesSum);
    }
}
