<?php

namespace Tests\Unit\Actions;

use App\Actions\Contracts\GenerateDepositRefundReceiptPdfAction;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerateDepositRefundReceiptPdfActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_execute_stores_document_on_contract_with_folio_meta(): void
    {
        Storage::fake('local');
        config(['filesystems.documents_disk' => 'local']);

        $contract = $this->createContractGraph();
        $document = app(GenerateDepositRefundReceiptPdfAction::class)->execute(
            contract: $contract,
            summary: [
                'folio' => 'DEV-2026-00001',
                'move_out_date' => '2026-08-06',
                'deposit_available' => 7500.0,
                'deposit_applied' => 5000.0,
                'deposit_refund' => 2500.0,
                'credit_refunded' => 0.0,
                'settlement_batch_id' => 'batch-1',
            ],
            refundExpenseId: 99,
            userId: null,
        );

        $this->assertSame(Contract::class, $document->documentable_type);
        $this->assertSame($contract->id, $document->documentable_id);
        $this->assertNull($document->category);
        $this->assertSame('CONTRACT_DOCUMENT', $document->type);
        $this->assertSame('deposit_refund_receipt', data_get($document->meta, 'kind'));
        $this->assertSame('DEV-2026-00001', data_get($document->meta, 'folio'));
        $this->assertSame('batch-1', data_get($document->meta, 'settlement_batch_id'));
        $this->assertSame(99, data_get($document->meta, 'refund_expense_id'));
        $this->assertContains('deposit_refund', $document->tags);
        $this->assertContains('generated', $document->tags);
        Storage::disk(config('filesystems.documents_disk', 'local'))->assertExists($document->path);
    }

    private function createContractGraph(): Contract
    {
        $organization = Organization::factory()->create();
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Plaza Reforma',
        ]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'name' => 'Depto 203',
        ]);
        $tenant = Tenant::factory()->create([
            'organization_id' => $organization->id,
            'full_name' => 'Juan Pérez García',
        ]);

        TenantContext::setOrganizationId($organization->id);

        return Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'rent_amount' => 9500,
            'deposit_amount' => 7500,
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-08-06',
            'status' => Contract::STATUS_ACTIVE,
        ]);
    }
}
