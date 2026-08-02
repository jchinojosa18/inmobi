<?php

namespace Tests\Feature\Contracts;

use App\Actions\Contracts\GenerateLeaseAgreementPdfAction;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\ContractDocumentCategory;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LeaseAgreementPdfTest extends TestCase
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

    public function test_generate_action_creates_document_when_landlord_configured(): void
    {
        Storage::fake('local');
        config(['filesystems.documents_disk' => 'local']);

        [$organization, $contract] = $this->createContractGraph();
        $this->configureLandlord($organization);

        $document = app(GenerateLeaseAgreementPdfAction::class)->execute($contract, null);

        $this->assertInstanceOf(Document::class, $document);
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'documentable_type' => Contract::class,
            'documentable_id' => $contract->id,
            'type' => 'CONTRACT_DOCUMENT',
            'category' => ContractDocumentCategory::Contract->value,
            'mime' => 'application/pdf',
        ]);
        $this->assertTrue((bool) data_get($document->meta, 'generated'));
        $this->assertSame('lease_agreement', data_get($document->meta, 'kind'));
        $this->assertContains('contract', $document->tags ?? []);
        Storage::disk('local')->assertExists($document->path);
    }

    public function test_generate_action_requires_landlord_name(): void
    {
        [, $contract] = $this->createContractGraph();

        $this->expectException(ValidationException::class);

        app(GenerateLeaseAgreementPdfAction::class)->execute($contract, null);
    }

    public function test_view_data_term_description_is_whole_months(): void
    {
        [$organization, $contract] = $this->createContractGraph();
        $this->configureLandlord($organization);

        $data = app(GenerateLeaseAgreementPdfAction::class)->viewData($contract);

        $this->assertSame('12 meses', $data['term_description']);
    }

    public function test_lease_agreement_view_includes_illicit_use_clause(): void
    {
        [$organization, $contract] = $this->createContractGraph();
        $this->configureLandlord($organization);

        $html = view('pdf.lease-agreement', app(GenerateLeaseAgreementPdfAction::class)->viewData($contract))->render();

        $this->assertStringContainsString('USO ILÍCITO DEL INMUEBLE', $html);
        $this->assertStringContainsString('trata de personas', $html);
        $this->assertStringContainsString('artículos 2297, 2298 y 2391', $html);
    }

    public function test_other_organization_user_gets_403_on_pdf_route(): void
    {
        Storage::fake('local');
        config(['filesystems.documents_disk' => 'local']);

        [$organization, $contract] = $this->createContractGraph();
        $this->configureLandlord($organization);

        $otherOrg = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $otherOrg->id]);

        $this->actingAs($user)
            ->get(route('contracts.agreement.pdf', ['contractId' => $contract->id]))
            ->assertForbidden();
    }

    public function test_unsigned_share_url_gets_403(): void
    {
        Storage::fake('local');
        config(['filesystems.documents_disk' => 'local']);

        [, $contract] = $this->createContractGraph();

        $this->get(route('contracts.agreement.share', ['contractId' => $contract->id]))
            ->assertForbidden();
    }

    public function test_tampered_share_url_gets_403(): void
    {
        Storage::fake('local');
        config(['filesystems.documents_disk' => 'local']);

        [$organization, $contract] = $this->createContractGraph();
        $this->configureLandlord($organization);

        app(GenerateLeaseAgreementPdfAction::class)->execute($contract, null);

        $relative = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'contracts.agreement.share',
            now()->addDays(7),
            ['contractId' => $contract->id],
            absolute: false,
        );
        $signedUrl = \Illuminate\Support\Facades\URL::to($relative);
        $tamperedUrl = str_replace('signature=', 'signature=tampered', $signedUrl);
        $pathWithQuery = parse_url($tamperedUrl, PHP_URL_PATH).'?'.parse_url($tamperedUrl, PHP_URL_QUERY);

        $this->get($pathWithQuery)
            ->assertForbidden();
    }

    public function test_authenticated_pdf_route_returns_pdf(): void
    {
        Storage::fake('local');
        config(['filesystems.documents_disk' => 'local']);

        [$organization, $contract] = $this->createContractGraph();
        $this->configureLandlord($organization);
        $user = User::factory()->create(['organization_id' => $organization->id]);

        app(GenerateLeaseAgreementPdfAction::class)->execute($contract, $user->id);

        $this->actingAs($user)
            ->get(route('contracts.agreement.pdf', ['contractId' => $contract->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_shared_pdf_route_accepts_signed_url(): void
    {
        Storage::fake('local');
        config(['filesystems.documents_disk' => 'local']);

        [$organization, $contract] = $this->createContractGraph();
        $this->configureLandlord($organization);

        app(GenerateLeaseAgreementPdfAction::class)->execute($contract, null);

        $relative = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'contracts.agreement.share',
            now()->addDays(7),
            ['contractId' => $contract->id],
            absolute: false,
        );
        $signedUrl = \Illuminate\Support\Facades\URL::to($relative);

        $pathWithQuery = parse_url($signedUrl, PHP_URL_PATH).'?'.parse_url($signedUrl, PHP_URL_QUERY);

        $this->get($pathWithQuery)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    /**
     * @return array{0: Organization, 1: Contract}
     */
    private function createContractGraph(): array
    {
        $organization = Organization::factory()->create();
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'address' => 'Calle Reforma 123, Ensenada, B.C.',
        ]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'name' => 'Depto 203',
        ]);
        $tenant = Tenant::factory()->create([
            'organization_id' => $organization->id,
            'full_name' => 'Juan Pérez García',
            'ine_clave' => 'ABC1234567890',
        ]);

        TenantContext::setOrganizationId($organization->id);

        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'rent_amount' => 9500,
            'deposit_amount' => 9500,
            'due_day' => 5,
            'starts_at' => '2026-08-01',
            'ends_at' => '2027-07-31',
            'status' => Contract::STATUS_ACTIVE,
        ]);

        return [$organization, $contract];
    }

    private function configureLandlord(Organization $organization): void
    {
        OrganizationSetting::query()
            ->withoutOrganizationScope()
            ->updateOrCreate(
                ['organization_id' => $organization->id],
                [
                    'landlord_name' => 'María López Hernández',
                    'landlord_rep' => 'Representante Legal',
                ],
            );
    }
}
