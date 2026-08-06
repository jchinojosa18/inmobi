<?php

namespace Tests\Unit\Actions;

use App\Actions\Contracts\GenerateDepositRefundReceiptFolioAction;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Organization;
use App\Support\ContractDocumentCategory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateDepositRefundReceiptFolioActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_folios_increment_per_organization_and_year(): void
    {
        $organization = Organization::factory()->create();
        $contract = Contract::factory()->create(['organization_id' => $organization->id]);

        Document::storeNew([
            'organization_id' => $organization->id,
            'documentable_type' => Contract::class,
            'documentable_id' => $contract->id,
            'path' => 'documents/contract/'.$organization->id.'/seed.pdf',
            'mime' => 'application/pdf',
            'size' => 10,
            'type' => 'CONTRACT_DOCUMENT',
            'category' => ContractDocumentCategory::Contract,
            'tags' => ['deposit_refund', 'generated'],
            'meta' => ['kind' => 'deposit_refund_receipt', 'folio' => 'DEV-2026-00001'],
        ]);

        $folio = app(GenerateDepositRefundReceiptFolioAction::class)
            ->execute($organization->id, CarbonImmutable::parse('2026-08-06'));

        $this->assertSame('DEV-2026-00002', $folio);
    }
}
