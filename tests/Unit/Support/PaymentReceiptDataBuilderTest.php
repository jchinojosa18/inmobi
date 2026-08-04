<?php

namespace Tests\Unit\Support;

use App\Models\Contract;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\PaymentReceiptDataBuilder;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentReceiptDataBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_includes_tenant_and_unit_without_tenant_context(): void
    {
        $organization = Organization::factory()->create();
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Plaza Norte',
        ]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'name' => 'Depto 204',
        ]);
        $tenant = Tenant::factory()->create([
            'organization_id' => $organization->id,
            'full_name' => 'Ana Pérez López',
        ]);
        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => Contract::STATUS_ENDED,
            'ends_at' => '2026-12-31',
            'rent_amount' => 0,
        ]);
        $payment = Payment::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'amount' => 1500,
            'receipt_folio' => 'REC-2026-001234',
        ]);

        TenantContext::clear();

        $receipt = app(PaymentReceiptDataBuilder::class)->build(
            Payment::query()->withoutOrganizationScope()->findOrFail($payment->id)
        );

        $this->assertSame('ANA PÉREZ LÓPEZ', $receipt['tenant_name']);
        $this->assertSame($property->fresh()->name, $receipt['property_name']);
        $this->assertSame('Depto 204', $receipt['unit_name']);
    }
}
