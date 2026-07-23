<?php

namespace Tests\Feature\FileViewer;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfInlineDispositionTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_receipt_pdf_inline_streams_inline_disposition(): void
    {
        [$user, $payment] = $this->createPaymentGraph();

        $this->actingAs($user)
            ->get(route('payments.receipt.pdf', ['paymentId' => $payment->id, 'inline' => 1]))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_payment_receipt_pdf_without_inline_downloads_attachment(): void
    {
        [$user, $payment] = $this->createPaymentGraph();

        $response = $this->actingAs($user)
            ->get(route('payments.receipt.pdf', ['paymentId' => $payment->id]));

        $response->assertOk();
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
    }

    public function test_deposit_receipt_pdf_honors_inline_parameter(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $contract = $this->createContract($organization);

        $charge = Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'meta' => ['deposit_receipt_folio' => 'DEP-2026-00099'],
        ]);

        $this->actingAs($user)
            ->get(route('deposits.receipt.pdf', ['chargeId' => $charge->id, 'inline' => 1]))
            ->assertOk();
    }

    /**
     * @return array{0: User, 1: Payment}
     */
    private function createPaymentGraph(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $contract = $this->createContract($organization);

        $payment = Payment::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'receipt_folio' => 'REC-2026-000123',
        ]);

        return [$user, $payment];
    }

    private function createContract(Organization $organization): Contract
    {
        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $tenant = Tenant::factory()->create(['organization_id' => $organization->id]);

        return Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
        ]);
    }
}
