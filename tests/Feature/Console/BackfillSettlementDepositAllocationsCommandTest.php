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
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillSettlementDepositAllocationsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_backfill_creates_deposit_payment_and_allocations_for_ended_settlement(): void
    {
        $organization = Organization::factory()->create();
        TenantContext::setOrganizationId((int) $organization->id);

        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $tenant = Tenant::factory()->create(['organization_id' => $organization->id]);

        $batchId = 'batch-backfill-test-001';

        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'rent_amount' => 0,
            'status' => Contract::STATUS_ENDED,
            'ends_at' => '2026-03-20',
            'meta' => [
                'settlement_batch_id' => $batchId,
                'settlements' => [
                    $batchId => [
                        'batch_id' => $batchId,
                        'move_out_date' => '2026-03-20',
                        'deposit_applied' => 400,
                        'moveout_charge_ids' => [],
                    ],
                ],
            ],
        ]);

        Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 400,
        ]);

        Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_DEPOSIT_APPLY,
            'period' => '2026-03',
            'charge_date' => '2026-03-20',
            'amount' => -400,
            'meta' => ['settlement_batch_id' => $batchId],
        ]);

        $moveout = Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_MOVEOUT,
            'period' => '2026-03',
            'charge_date' => '2026-03-20',
            'amount' => 400,
            'meta' => ['settlement_batch_id' => $batchId],
        ]);

        $this->artisan('inmo:settlements:backfill-deposit-allocations', [
            '--contract' => $contract->id,
        ])
            ->assertExitCode(0);

        $depositPayment = Payment::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->where('method', Payment::METHOD_DEPOSIT)
            ->first();

        $this->assertNotNull($depositPayment);
        $this->assertSame($batchId, data_get($depositPayment->meta, 'settlement_batch_id'));
        $this->assertSame(
            400.0,
            round((float) PaymentAllocation::query()
                ->withoutOrganizationScope()
                ->where('charge_id', $moveout->id)
                ->sum('amount'), 2)
        );

        // Idempotent second run.
        $this->artisan('inmo:settlements:backfill-deposit-allocations', [
            '--contract' => $contract->id,
        ])
            ->assertExitCode(0);

        $this->assertSame(
            1,
            Payment::query()
                ->withoutOrganizationScope()
                ->where('contract_id', $contract->id)
                ->where('method', Payment::METHOD_DEPOSIT)
                ->count()
        );
    }

    public function test_dry_run_does_not_create_payment(): void
    {
        $organization = Organization::factory()->create();
        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $tenant = Tenant::factory()->create(['organization_id' => $organization->id]);
        $batchId = 'batch-dry-run-001';

        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'rent_amount' => 0,
            'status' => Contract::STATUS_ENDED,
            'ends_at' => '2026-03-20',
            'meta' => [
                'settlement_batch_id' => $batchId,
                'settlements' => [
                    $batchId => [
                        'batch_id' => $batchId,
                        'move_out_date' => '2026-03-20',
                        'deposit_applied' => 100,
                    ],
                ],
            ],
        ]);

        Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_MOVEOUT,
            'period' => '2026-03',
            'charge_date' => '2026-03-20',
            'amount' => 100,
        ]);

        $this->artisan('inmo:settlements:backfill-deposit-allocations', [
            '--contract' => $contract->id,
            '--dry-run' => true,
        ])
            ->assertExitCode(0);

        $this->assertSame(
            0,
            Payment::query()
                ->withoutOrganizationScope()
                ->where('contract_id', $contract->id)
                ->where('method', Payment::METHOD_DEPOSIT)
                ->count()
        );
    }
}
