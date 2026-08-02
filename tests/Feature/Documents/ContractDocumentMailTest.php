<?php

namespace Tests\Feature\Documents;

use App\Mail\ContractDocumentMail;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\ContractDocumentCategory;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContractDocumentMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_attaches_stored_document_bytes_not_regenerated_pdf(): void
    {
        Storage::fake('local');
        TenantContext::clear();

        $organization = Organization::factory()->create();
        OrganizationSetting::query()
            ->withoutOrganizationScope()
            ->updateOrCreate(
                ['organization_id' => $organization->id],
                [
                    'contract_email_template' => 'Hola {tenant_name} {shared_contract_url}',
                    'landlord_name' => 'Arrendador SA',
                ],
            );

        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'name' => 'Depto 3',
        ]);
        $tenant = Tenant::factory()->create([
            'organization_id' => $organization->id,
            'full_name' => 'Ana Guest',
            'email' => 'ana@example.com',
        ]);
        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
        ]);

        $marker = 'STORED-CONTRACT-PDF-BYTES-UNIQUE';
        $path = 'documents/contract/'.$organization->id.'/stored.pdf';
        Storage::disk('local')->put($path, $marker);

        $document = Document::factory()->create([
            'organization_id' => $organization->id,
            'documentable_type' => Contract::class,
            'documentable_id' => $contract->id,
            'category' => ContractDocumentCategory::Contract->value,
            'type' => 'CONTRACT_DOCUMENT',
            'mime' => 'application/pdf',
            'path' => $path,
            'meta' => ['disk' => 'local'],
        ]);

        $built = (new ContractDocumentMail($document))->build();
        $rawAttachments = $built->rawAttachments;
        $this->assertNotEmpty($rawAttachments);
        $this->assertSame($marker, $rawAttachments[0]['data'] ?? null);
        $this->assertSame('contrato-'.$document->id.'.pdf', $rawAttachments[0]['name'] ?? null);
    }
}
