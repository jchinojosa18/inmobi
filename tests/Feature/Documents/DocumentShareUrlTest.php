<?php

namespace Tests\Feature\Documents;

use App\Models\Contract;
use App\Models\Document;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\ContractDocumentCategory;
use App\Support\DocumentShareUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentShareUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_document_streams_for_guest_with_valid_signature(): void
    {
        Storage::fake('local');
        $document = $this->makeContractCategoryDocument(contents: '%PDF-1.4 shared-contract');

        $shareUrl = DocumentShareUrl::make($document->id);
        $pathWithQuery = parse_url($shareUrl, PHP_URL_PATH).'?'.parse_url($shareUrl, PHP_URL_QUERY);

        $response = $this->get($pathWithQuery);

        $response->assertOk()
            ->assertHeader('content-disposition', 'inline');
        $this->assertStringContainsString('shared-contract', $response->streamedContent());
    }

    public function test_shared_document_rejects_unsigned_url(): void
    {
        Storage::fake('local');
        $document = $this->makeContractCategoryDocument();

        $this->get('/documents/'.$document->id.'/shared')->assertForbidden();
    }

    public function test_shared_document_rejects_non_contract_category(): void
    {
        Storage::fake('local');
        $document = $this->makeContractCategoryDocument(
            category: ContractDocumentCategory::Id,
        );

        $shareUrl = DocumentShareUrl::make($document->id);
        $pathWithQuery = parse_url($shareUrl, PHP_URL_PATH).'?'.parse_url($shareUrl, PHP_URL_QUERY);

        $this->get($pathWithQuery)->assertNotFound();
    }

    public function test_shared_document_returns_not_found_when_file_missing_on_disk(): void
    {
        Storage::fake('local');
        $document = $this->makeContractCategoryDocument();
        Storage::disk('local')->delete($document->path);

        $shareUrl = DocumentShareUrl::make($document->id);
        $pathWithQuery = parse_url($shareUrl, PHP_URL_PATH).'?'.parse_url($shareUrl, PHP_URL_QUERY);

        $this->get($pathWithQuery)->assertNotFound();
    }

    private function makeContractCategoryDocument(
        string $contents = '%PDF-1.4 test',
        ContractDocumentCategory $category = ContractDocumentCategory::Contract,
    ): Document {
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
        ]);

        $path = 'documents/contract/'.$organization->id.'/contrato-share.pdf';
        Storage::disk('local')->put($path, $contents);

        return Document::factory()->create([
            'organization_id' => $organization->id,
            'documentable_type' => Contract::class,
            'documentable_id' => $contract->id,
            'category' => $category->value,
            'type' => 'CONTRACT_DOCUMENT',
            'mime' => 'application/pdf',
            'path' => $path,
            'meta' => ['disk' => 'local'],
        ]);
    }
}
