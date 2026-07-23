<?php

namespace Tests\Unit\Support;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\TenantKardexSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantKardexSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_aggregates_kpis_across_tenant_contracts(): void
    {
        $organization = Organization::factory()->create();
        $tenant = Tenant::factory()->create(['organization_id' => $organization->id]);

        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unitA = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'name' => '402',
        ]);
        $unitB = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'name' => 'Local 3',
        ]);

        $active = Contract::withoutEvents(fn () => Contract::factory()->create([
            'organization_id' => $organization->id,
            'tenant_id' => $tenant->id,
            'unit_id' => $unitA->id,
            'status' => Contract::STATUS_ACTIVE,
            'rent_amount' => 12500,
        ]));
        $ended = Contract::factory()->create([
            'organization_id' => $organization->id,
            'tenant_id' => $tenant->id,
            'unit_id' => $unitB->id,
            'status' => Contract::STATUS_ENDED,
            'rent_amount' => 8000,
            'ends_at' => '2025-02-28',
        ]);

        $rent = Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $active->id,
            'unit_id' => $active->unit_id,
            'type' => Charge::TYPE_RENT,
            'amount' => 12500,
            'charge_date' => '2026-07-01',
        ]);
        Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $active->id,
            'unit_id' => $active->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'amount' => 12500,
            'charge_date' => '2026-01-01',
        ]);

        $payment = Payment::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $active->id,
            'amount' => 8000,
            'paid_at' => '2026-07-03 12:00:00',
        ]);
        PaymentAllocation::factory()->create([
            'organization_id' => $organization->id,
            'payment_id' => $payment->id,
            'charge_id' => $rent->id,
            'amount' => 8000,
        ]);

        Payment::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $ended->id,
            'amount' => 8000,
            'paid_at' => '2025-02-01 12:00:00',
        ]);

        CreditBalance::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $active->id,
            'balance' => 200,
        ]);

        $summary = TenantKardexSummary::for($tenant);

        $this->assertSame(1, $summary->activeContractsCount());
        $this->assertSame(4500.0, $summary->pendingBalance());
        $this->assertSame(200.0, $summary->creditBalance());
        $this->assertSame(16000.0, $summary->totalPaid());
        $this->assertCount(2, $summary->contracts());
        $this->assertCount(1, $summary->outstandingCharges());
        $this->assertSame(4500.0, (float) $summary->outstandingCharges()->first()['balance']);
        $this->assertCount(2, $summary->recentPayments());
    }

    public function test_deposit_apply_is_excluded_from_pending_balance(): void
    {
        $organization = Organization::factory()->create();
        $tenant = Tenant::factory()->create(['organization_id' => $organization->id]);
        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $contract = Contract::withoutEvents(fn () => Contract::factory()->create([
            'organization_id' => $organization->id,
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'status' => Contract::STATUS_ACTIVE,
        ]));

        Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_DEPOSIT_APPLY,
            'amount' => 500,
            'charge_date' => '2026-07-01',
        ]);

        $summary = TenantKardexSummary::for($tenant);

        $this->assertSame(0.0, $summary->pendingBalance());
        $this->assertCount(0, $summary->outstandingCharges());
    }
}
