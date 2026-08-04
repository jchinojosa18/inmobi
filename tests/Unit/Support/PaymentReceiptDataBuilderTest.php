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

    public function test_build_includes_contract_pending_balance_after_payment(): void
    {
        [$payment] = $this->makeReceiptGraph(
            rentAmount: 10000,
            paidAmount: 4000,
            creditedAmount: 0,
        );

        TenantContext::clear();

        $receipt = app(PaymentReceiptDataBuilder::class)->build(
            Payment::query()->withoutOrganizationScope()->findOrFail($payment->id)
        );

        $this->assertSame(6000.0, $receipt['pending_balance']);
        $this->assertSame(0.0, $receipt['credited_amount']);
    }

    public function test_pdf_hides_credit_line_when_no_credit_and_shows_pending_balance(): void
    {
        [$payment] = $this->makeReceiptGraph(
            rentAmount: 5000,
            paidAmount: 5000,
            creditedAmount: 0,
        );

        TenantContext::clear();

        $receipt = app(PaymentReceiptDataBuilder::class)->build(
            Payment::query()->withoutOrganizationScope()->findOrFail($payment->id)
        );

        $html = view('pdf.payment-receipt', ['receipt' => $receipt])->render();

        $this->assertStringContainsString('Saldo:', $html);
        $this->assertStringContainsString('$0.00', $html);
        $this->assertStringNotContainsString('Saldo a favor generado:', $html);
    }

    public function test_pdf_shows_credit_line_when_credit_was_generated(): void
    {
        [$payment] = $this->makeReceiptGraph(
            rentAmount: 1000,
            paidAmount: 1500,
            creditedAmount: 500,
        );

        TenantContext::clear();

        $receipt = app(PaymentReceiptDataBuilder::class)->build(
            Payment::query()->withoutOrganizationScope()->findOrFail($payment->id)
        );

        $html = view('pdf.payment-receipt', ['receipt' => $receipt])->render();

        $this->assertStringContainsString('Saldo a favor generado:', $html);
        $this->assertStringContainsString('$500.00', $html);
        $this->assertStringContainsString('Saldo:', $html);
        $this->assertTrue(
            strpos($html, 'Saldo:') < strpos($html, 'Saldo a favor generado:'),
            'Saldo should appear above saldo a favor'
        );
    }

    public function test_pdf_explains_credit_only_payment_without_allocations(): void
    {
        [$payment] = $this->makeReceiptGraph(
            rentAmount: 0,
            paidAmount: 1000,
            creditedAmount: 1000,
        );

        TenantContext::clear();

        $receipt = app(PaymentReceiptDataBuilder::class)->build(
            Payment::query()->withoutOrganizationScope()->findOrFail($payment->id)
        );

        $html = view('pdf.payment-receipt', ['receipt' => $receipt])->render();

        $this->assertSame([], $receipt['allocations']);
        $this->assertStringContainsString('Sin cargos pendientes; el monto quedó como saldo a favor.', $html);
        $this->assertStringNotContainsString('Sin allocations registradas.', $html);
    }

    /**
     * @return array{0: Payment, 1: Contract}
     */
    private function makeReceiptGraph(float $rentAmount, float $paidAmount, float $creditedAmount): array
    {
        $organization = Organization::factory()->create();
        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $tenant = Tenant::factory()->create(['organization_id' => $organization->id]);
        $contract = Contract::withoutEvents(fn () => Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => Contract::STATUS_ACTIVE,
            'rent_amount' => $rentAmount,
            'active_lock' => 1,
        ]));

        $rent = Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_RENT,
            'period' => '2026-08',
            'charge_date' => '2026-08-01',
            'amount' => $rentAmount,
        ]);

        $allocated = min($paidAmount, $rentAmount);

        $payment = Payment::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'amount' => $paidAmount,
            'receipt_folio' => 'REC-TEST-0001',
            'meta' => [
                'credited_amount' => $creditedAmount,
            ],
        ]);

        if ($allocated > 0) {
            PaymentAllocation::factory()->create([
                'organization_id' => $organization->id,
                'payment_id' => $payment->id,
                'charge_id' => $rent->id,
                'amount' => $allocated,
            ]);
        }

        return [$payment, $contract];
    }
}
