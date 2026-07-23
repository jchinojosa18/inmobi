<?php

namespace Tests\Feature\Payments;

use App\Models\Contract;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\PaymentReceiptShareUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PaymentReceiptShareUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_receipt_accepts_relative_signature_across_hosts(): void
    {
        $payment = $this->makePaymentWithFolio();

        URL::forceRootUrl('http://127.0.0.1');
        $shareUrl = PaymentReceiptShareUrl::make($payment->id);

        $pathWithQuery = parse_url($shareUrl, PHP_URL_PATH).'?'.parse_url($shareUrl, PHP_URL_QUERY);

        $this->get($pathWithQuery)->assertOk();
    }

    public function test_shared_receipt_works_even_when_logged_in_as_other_organization(): void
    {
        $payment = $this->makePaymentWithFolio();
        $otherOrg = Organization::factory()->create();
        $otherUser = \App\Models\User::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);

        $shareUrl = PaymentReceiptShareUrl::make($payment->id);
        $pathWithQuery = parse_url($shareUrl, PHP_URL_PATH).'?'.parse_url($shareUrl, PHP_URL_QUERY);

        $this->actingAs($otherUser)
            ->get($pathWithQuery)
            ->assertOk();
    }

    public function test_shared_receipt_streams_inline_for_browser_viewing(): void
    {
        $payment = $this->makePaymentWithFolio();
        $shareUrl = PaymentReceiptShareUrl::make($payment->id);
        $pathWithQuery = parse_url($shareUrl, PHP_URL_PATH).'?'.parse_url($shareUrl, PHP_URL_QUERY);

        $response = $this->get($pathWithQuery);

        $response->assertOk();
        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
    }

    public function test_shared_receipt_rejects_unsigned_url(): void
    {
        $payment = $this->makePaymentWithFolio();

        $this->get('/receipts/'.$payment->id.'/shared.pdf')->assertForbidden();
    }

    private function makePaymentWithFolio(): Payment
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
            'status' => Contract::STATUS_ENDED,
            'ends_at' => '2026-12-31',
            'rent_amount' => 0,
        ]);

        return Payment::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'amount' => 1000,
            'receipt_folio' => 'REC-2026-009999',
        ]);
    }
}
