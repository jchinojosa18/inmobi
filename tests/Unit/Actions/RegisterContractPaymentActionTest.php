<?php

namespace Tests\Unit\Actions;

use App\Actions\Payments\RegisterContractPaymentAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterContractPaymentActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_it_generates_unique_sequential_receipt_folios(): void
    {
        [$organization, $contract] = $this->makeContractGraph();
        TenantContext::setOrganizationId($organization->id);

        $action = app(RegisterContractPaymentAction::class);

        $firstPayment = $action->execute($contract, [
            'amount' => 500,
            'method' => 'TRANSFER',
            'paid_at' => '2026-03-04 10:00:00',
            'reference' => 'A-001',
        ]);

        $secondPayment = $action->execute($contract, [
            'amount' => 300,
            'method' => 'CASH',
            'paid_at' => '2026-03-05 12:00:00',
            'reference' => 'A-002',
        ]);

        $this->assertSame('REC-2026-000001', $firstPayment->receipt_folio);
        $this->assertSame('REC-2026-000002', $secondPayment->receipt_folio);
        $this->assertNotSame($firstPayment->receipt_folio, $secondPayment->receipt_folio);
    }

    public function test_it_applies_allocations_when_registering_payment(): void
    {
        [$organization, $contract, $unit] = $this->makeContractGraph();
        TenantContext::setOrganizationId($organization->id);

        $charge = Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_RENT,
            'period' => '2026-03',
            'charge_date' => '2026-03-05',
            'amount' => 1000,
            'meta' => null,
        ]);

        $payment = app(RegisterContractPaymentAction::class)->execute($contract, [
            'amount' => 700,
            'method' => 'TRANSFER',
            'paid_at' => '2026-03-06 11:00:00',
            'reference' => 'TRX-7781',
        ]);

        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $payment->id,
            'charge_id' => $charge->id,
            'amount' => '700.00',
        ]);

        $this->assertSame(1, PaymentAllocation::query()->where('payment_id', $payment->id)->count());
        $this->assertTrue((bool) data_get($payment->fresh()->meta, 'allocation_processed'));
    }

    public function test_it_uses_organization_folio_configuration_when_available(): void
    {
        [$organization, $contract] = $this->makeContractGraph();
        TenantContext::setOrganizationId($organization->id);

        OrganizationSetting::factory()->create([
            'organization_id' => $organization->id,
            'receipt_folio_mode' => OrganizationSetting::RECEIPT_MODE_CONTINUOUS,
            'receipt_folio_prefix' => 'FAC',
            'receipt_folio_padding' => 4,
        ]);

        $action = app(RegisterContractPaymentAction::class);

        $firstPayment = $action->execute($contract, [
            'amount' => 500,
            'method' => 'TRANSFER',
            'paid_at' => '2026-12-31 10:00:00',
            'reference' => 'F-001',
        ]);

        $secondPayment = $action->execute($contract, [
            'amount' => 300,
            'method' => 'TRANSFER',
            'paid_at' => '2027-01-01 10:00:00',
            'reference' => 'F-002',
        ]);

        $this->assertSame('FAC-0001', $firstPayment->receipt_folio);
        $this->assertSame('FAC-0002', $secondPayment->receipt_folio);
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
            'status' => Contract::STATUS_ENDED,
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-12-31',
        ]);

        return [$organization, $contract, $unit];
    }
}
