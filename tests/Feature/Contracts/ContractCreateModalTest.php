<?php

namespace Tests\Feature\Contracts;

use App\Livewire\Contracts\CreateModal;
use App\Mail\ContractAgreementMail;
use App\Models\Charge;
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
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ContractCreateModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_create_form_starts_with_empty_numeric_fields(): void
    {
        [$organization, $occupiedContract, $user] = $this->createContractGraph();

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->dispatch('open-contract-create')
            ->assertSet('deposit_amount', '')
            ->assertSet('due_day', '')
            ->assertSet('grace_days', '')
            ->assertSet('penalty_rate_daily', '');
    }

    public function test_edit_modal_loads_contract_data(): void
    {
        [$organization, $contract, $user] = $this->createContractGraph();

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->dispatch('open-contract-edit', contractId: $contract->id)
            ->assertSet('open', true)
            ->assertSet('contractId', $contract->id)
            ->assertSet('unit_id', $contract->unit_id)
            ->assertSet('tenant_id', $contract->tenant_id)
            ->assertSet('rent_amount', (string) $contract->rent_amount)
            ->assertSet('penalty_rate_daily', '5.00');
    }

    public function test_edit_modal_updates_contract_and_dispatches_event(): void
    {
        [$organization, $contract, $user] = $this->createContractGraph();

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->dispatch('open-contract-edit', contractId: $contract->id)
            ->set('rent_amount', '12500')
            ->set('meta_notes', 'Nota actualizada')
            ->call('save')
            ->assertSet('open', false)
            ->assertDispatched('contract-updated');

        $contract->refresh();

        $this->assertSame('12500.00', (string) $contract->rent_amount);
        $this->assertSame('Nota actualizada', data_get($contract->meta, 'notes'));
    }

    public function test_edit_modal_due_day_syncs_current_month_rent_charge(): void
    {
        CarbonImmutable::setTestNow('2026-03-15 09:00:00');

        [$organization, $contract, $user] = $this->createContractGraph();

        $charge = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_RENT)
            ->where('period', '2026-03')
            ->first();

        $this->assertNotNull($charge);

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->dispatch('open-contract-edit', contractId: $contract->id)
            ->set('due_day', '18')
            ->set('grace_days', '2')
            ->call('save')
            ->assertSet('open', false);

        $charge->refresh();

        $this->assertSame(18, (int) $contract->fresh()->due_day);
        $this->assertSame('2026-03-18', $charge->due_date?->format('Y-m-d'));
        $this->assertSame('2026-03-20', $charge->grace_until?->format('Y-m-d'));

        CarbonImmutable::setTestNow();
    }

    public function test_component_is_mounted_in_layout_on_contract_show(): void
    {
        [$organization, $contract, $user] = $this->createContractGraph();

        $this->actingAs($user)
            ->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSeeLivewire(CreateModal::class);
    }

    public function test_create_select_excludes_units_with_active_contract(): void
    {
        [$organization, $occupiedContract, $user] = $this->createContractGraph();
        $occupiedUnit = Unit::query()->withoutOrganizationScope()->findOrFail($occupiedContract->unit_id);

        $freeUnit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $occupiedUnit->property_id,
            'status' => 'active',
            'name' => 'Free Unit ZZ',
            'code' => 'FREE-1',
        ]);

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->dispatch('open-contract-create')
            ->assertSet('open', true)
            ->assertViewHas('units', function ($units) use ($occupiedContract, $freeUnit): bool {
                $ids = $units->pluck('id')->all();

                return ! in_array($occupiedContract->unit_id, $ids, true)
                    && in_array($freeUnit->id, $ids, true);
            })
            ->assertSee('Free Unit ZZ')
            ->assertDontSee($occupiedUnit->name);
    }

    public function test_open_create_does_not_preselect_occupied_unit(): void
    {
        [$organization, $occupiedContract, $user] = $this->createContractGraph();

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->dispatch('open-contract-create', unitId: $occupiedContract->unit_id)
            ->assertSet('open', true)
            ->assertSet('unit_id', null);
    }

    public function test_edit_modal_locks_unit_and_tenant_fields(): void
    {
        [$organization, $contract, $user] = $this->createContractGraph();

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->dispatch('open-contract-edit', contractId: $contract->id)
            ->assertSeeHtml('disabled="disabled"')
            ->assertSeeHtml('wire:model="unit_id"')
            ->assertSeeHtml('wire:model="tenant_id"');
    }

    public function test_create_generates_contract_category_document(): void
    {
        Storage::fake('local');
        config(['filesystems.documents_disk' => 'local']);

        [$organization, $user, $unit, $tenant] = $this->createOpenCreateGraph();
        $this->seedLandlord($organization->id);

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->dispatch('open-contract-create')
            ->set('unit_id', $unit->id)
            ->set('tenant_id', $tenant->id)
            ->set('rent_amount', '10000')
            ->set('deposit_amount', '10000')
            ->set('due_day', '5')
            ->set('grace_days', '3')
            ->set('penalty_rate_daily', '5')
            ->set('status', Contract::STATUS_ACTIVE)
            ->set('starts_at', '2026-08-01')
            ->set('ends_at', '2027-07-31')
            ->call('save')
            ->assertHasNoErrors();

        $contract = Contract::query()->withoutOrganizationScope()
            ->where('unit_id', $unit->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        $this->assertNotNull($contract);

        $this->assertTrue(
            Document::query()->withoutOrganizationScope()
                ->where('documentable_type', Contract::class)
                ->where('documentable_id', $contract->id)
                ->where('category', ContractDocumentCategory::Contract->value)
                ->exists()
        );
    }

    public function test_create_requires_ends_at(): void
    {
        [$organization, $user, $unit, $tenant] = $this->createOpenCreateGraph();
        $this->seedLandlord($organization->id);

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->dispatch('open-contract-create')
            ->set('unit_id', $unit->id)
            ->set('tenant_id', $tenant->id)
            ->set('rent_amount', '10000')
            ->set('deposit_amount', '10000')
            ->set('due_day', '5')
            ->set('grace_days', '3')
            ->set('penalty_rate_daily', '5')
            ->set('starts_at', '2026-08-01')
            ->set('ends_at', null)
            ->call('save')
            ->assertHasErrors(['ends_at']);
    }

    public function test_create_send_email_dispatches_contract_agreement_mail(): void
    {
        Mail::fake();
        Storage::fake('local');
        config(['filesystems.documents_disk' => 'local']);

        [$organization, $user, $unit, $tenant] = $this->createOpenCreateGraph();
        $this->seedLandlord($organization->id);
        Permission::findOrCreate('receipts.send', 'web');
        $user->givePermissionTo('receipts.send');

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->dispatch('open-contract-create')
            ->set('unit_id', $unit->id)
            ->set('tenant_id', $tenant->id)
            ->set('rent_amount', '10000')
            ->set('deposit_amount', '10000')
            ->set('due_day', '5')
            ->set('grace_days', '3')
            ->set('penalty_rate_daily', '5')
            ->set('starts_at', '2026-08-01')
            ->set('ends_at', '2027-07-31')
            ->set('send_email', true)
            ->call('save')
            ->assertSet('step', 'done')
            ->assertNotSet('shareUrl', null)
            ->assertNotSet('pdfUrl', null);

        Mail::assertSent(ContractAgreementMail::class, fn (ContractAgreementMail $mail) => $mail->hasTo('tenant@example.com'));
    }

    public function test_create_done_builds_whatsapp_url_with_share_link(): void
    {
        Storage::fake('local');
        config(['filesystems.documents_disk' => 'local']);

        [$organization, $user, $unit, $tenant] = $this->createOpenCreateGraph();
        $tenant->update(['phone' => '526641112233']);
        $this->seedLandlord($organization->id);

        $component = Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->dispatch('open-contract-create')
            ->set('unit_id', $unit->id)
            ->set('tenant_id', $tenant->id)
            ->set('rent_amount', '10000')
            ->set('deposit_amount', '10000')
            ->set('due_day', '5')
            ->set('grace_days', '3')
            ->set('penalty_rate_daily', '5')
            ->set('starts_at', '2026-08-01')
            ->set('ends_at', '2027-07-31')
            ->set('send_email', false)
            ->call('save');

        $this->assertSame('done', $component->get('step'));
        $this->assertStringContainsString('wa.me', (string) $component->get('whatsAppUrl'));
        $this->assertStringContainsString('/agreement/shared.pdf', (string) $component->get('shareUrl'));
    }

    public function test_create_requires_landlord_name(): void
    {
        Storage::fake('local');
        [$organization, $user, $unit, $tenant] = $this->createOpenCreateGraph();

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->dispatch('open-contract-create')
            ->set('unit_id', $unit->id)
            ->set('tenant_id', $tenant->id)
            ->set('rent_amount', '10000')
            ->set('deposit_amount', '10000')
            ->set('due_day', '5')
            ->set('grace_days', '3')
            ->set('penalty_rate_daily', '5')
            ->set('starts_at', '2026-08-01')
            ->set('ends_at', '2027-07-31')
            ->call('save')
            ->assertHasErrors(['landlord_name']);

        $this->assertSame(0, Contract::query()->withoutOrganizationScope()->where('unit_id', $unit->id)->count());
    }

    public function test_edit_replaces_generated_contract_category_document(): void
    {
        Storage::fake('local');
        config(['filesystems.documents_disk' => 'local']);

        [$organization, $contract, $user] = $this->createContractGraph();
        $this->seedLandlord($organization->id);

        $oldPath = 'documents/contract/'.$organization->id.'/old.pdf';
        Storage::disk('local')->put($oldPath, 'OLD');
        $old = Document::factory()->create([
            'organization_id' => $organization->id,
            'documentable_type' => Contract::class,
            'documentable_id' => $contract->id,
            'category' => ContractDocumentCategory::Contract->value,
            'type' => 'CONTRACT_DOCUMENT',
            'mime' => 'application/pdf',
            'path' => $oldPath,
            'tags' => ['contract', 'generated', 'lease_agreement'],
            'meta' => [
                'disk' => 'local',
                'generated' => true,
                'kind' => 'lease_agreement',
            ],
        ]);

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->dispatch('open-contract-edit', contractId: $contract->id)
            ->set('ends_at', '2027-12-31')
            ->set('rent_amount', '12500')
            ->call('save')
            ->assertSet('open', false)
            ->assertHasNoErrors();

        $this->assertSoftDeleted('documents', ['id' => $old->id]);
        $this->assertFalse(Storage::disk('local')->exists($oldPath));

        $remaining = Document::query()->withoutOrganizationScope()
            ->where('documentable_type', Contract::class)
            ->where('documentable_id', $contract->id)
            ->where('category', ContractDocumentCategory::Contract->value)
            ->get();

        $this->assertCount(1, $remaining);
        $this->assertNotSame($old->id, $remaining->first()->id);
        $this->assertTrue($remaining->first()->isGeneratedLeaseAgreement());
    }

    public function test_edit_preserves_manual_upload_contract_document(): void
    {
        Storage::fake('local');
        config(['filesystems.documents_disk' => 'local']);

        [$organization, $contract, $user] = $this->createContractGraph();
        $this->seedLandlord($organization->id);

        $manualPath = 'documents/contract/'.$organization->id.'/manual.pdf';
        Storage::disk('local')->put($manualPath, 'MANUAL');
        $manual = Document::factory()->create([
            'organization_id' => $organization->id,
            'documentable_type' => Contract::class,
            'documentable_id' => $contract->id,
            'category' => ContractDocumentCategory::Contract->value,
            'type' => 'CONTRACT_DOCUMENT',
            'mime' => 'application/pdf',
            'path' => $manualPath,
            'tags' => ['contract', 'manual-upload'],
            'meta' => ['disk' => 'local'],
        ]);

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->dispatch('open-contract-edit', contractId: $contract->id)
            ->set('ends_at', '2027-12-31')
            ->set('rent_amount', '12500')
            ->call('save')
            ->assertSet('open', true)
            ->assertHasErrors(['contract_document']);

        $manual->refresh();

        $this->assertNull($manual->deleted_at);
        $this->assertTrue(Storage::disk('local')->exists($manualPath));

        $this->assertSame(1, Document::query()->withoutOrganizationScope()
            ->where('documentable_type', Contract::class)
            ->where('documentable_id', $contract->id)
            ->where('category', ContractDocumentCategory::Contract->value)
            ->count());
    }

    public function test_edit_without_generate_pdf_preserves_existing_document(): void
    {
        Storage::fake('local');
        config(['filesystems.documents_disk' => 'local']);

        [$organization, $contract, $user] = $this->createContractGraph();
        $this->seedLandlord($organization->id);

        $path = 'documents/contract/'.$organization->id.'/existing.pdf';
        Storage::disk('local')->put($path, 'pdf-bytes');

        $existing = Document::factory()->create([
            'organization_id' => $organization->id,
            'documentable_type' => Contract::class,
            'documentable_id' => $contract->id,
            'category' => ContractDocumentCategory::Contract->value,
            'type' => 'CONTRACT_DOCUMENT',
            'mime' => 'application/pdf',
            'path' => $path,
            'tags' => ['contract', 'generated', 'lease_agreement'],
            'meta' => [
                'disk' => 'local',
                'generated' => true,
                'kind' => 'lease_agreement',
            ],
        ]);

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->dispatch('open-contract-edit', contractId: $contract->id)
            ->set('rent_amount', '12500')
            ->set('generate_pdf', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('open', false);

        $existing->refresh();
        $this->assertNull($existing->deleted_at);
        $this->assertSame($path, $existing->path);
        $this->assertSame('12500.00', (string) $contract->fresh()->rent_amount);
        $this->assertSame(1, Document::query()->withoutOrganizationScope()
            ->where('documentable_type', Contract::class)
            ->where('documentable_id', $contract->id)
            ->where('category', ContractDocumentCategory::Contract->value)
            ->count());
    }

    public function test_open_create_defaults_generate_pdf_to_true(): void
    {
        [$organization, $occupiedContract, $user] = $this->createContractGraph();

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->dispatch('open-contract-create')
            ->assertSet('generate_pdf', true);
    }

    public function test_create_without_generate_pdf_skips_document_and_share_actions(): void
    {
        Mail::fake();
        Storage::fake('local');
        config(['filesystems.documents_disk' => 'local']);

        [$organization, $user, $unit, $tenant] = $this->createOpenCreateGraph();
        // Intentionally do NOT seed landlord — create must succeed without PDF.
        Permission::findOrCreate('receipts.send', 'web');
        $user->givePermissionTo('receipts.send');

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->dispatch('open-contract-create')
            ->set('unit_id', $unit->id)
            ->set('tenant_id', $tenant->id)
            ->set('rent_amount', '10000')
            ->set('deposit_amount', '10000')
            ->set('due_day', '5')
            ->set('grace_days', '3')
            ->set('penalty_rate_daily', '5')
            ->set('starts_at', '2026-08-01')
            ->set('ends_at', '2027-07-31')
            ->set('generate_pdf', false)
            ->set('send_email', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('step', 'done')
            ->assertSet('pdfUrl', null)
            ->assertSet('shareUrl', null)
            ->assertSet('whatsAppUrl', null)
            ->assertNotSet('createdContractId', null);

        $contract = Contract::query()->withoutOrganizationScope()
            ->where('unit_id', $unit->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        $this->assertNotNull($contract);
        $this->assertFalse(
            Document::query()->withoutOrganizationScope()
                ->where('documentable_type', Contract::class)
                ->where('documentable_id', $contract->id)
                ->where('category', ContractDocumentCategory::Contract->value)
                ->exists()
        );
        Mail::assertNothingSent();
    }

    public function test_unchecking_generate_pdf_clears_send_email(): void
    {
        [$organization, $user, $unit, $tenant] = $this->createOpenCreateGraph();
        Permission::findOrCreate('receipts.send', 'web');
        $user->givePermissionTo('receipts.send');

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->dispatch('open-contract-create')
            ->set('tenant_id', $tenant->id)
            ->assertSet('send_email', true)
            ->set('generate_pdf', false)
            ->assertSet('send_email', false);
    }

    public function test_open_create_defaults_send_email_to_false(): void
    {
        [$organization, $occupiedContract, $user] = $this->createContractGraph();
        Permission::findOrCreate('receipts.send', 'web');
        $user->givePermissionTo('receipts.send');

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->dispatch('open-contract-create')
            ->assertSet('send_email', false);
    }

    public function test_create_does_not_send_email_when_send_email_is_false(): void
    {
        Mail::fake();
        Storage::fake('local');
        config(['filesystems.documents_disk' => 'local']);

        [$organization, $user, $unit, $tenant] = $this->createOpenCreateGraph();
        $this->seedLandlord($organization->id);
        Permission::findOrCreate('receipts.send', 'web');
        $user->givePermissionTo('receipts.send');

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->dispatch('open-contract-create')
            ->set('unit_id', $unit->id)
            ->set('tenant_id', $tenant->id)
            ->set('send_email', false)
            ->set('rent_amount', '10000')
            ->set('deposit_amount', '10000')
            ->set('due_day', '5')
            ->set('grace_days', '3')
            ->set('penalty_rate_daily', '5')
            ->set('starts_at', '2026-08-01')
            ->set('ends_at', '2027-07-31')
            ->call('save')
            ->assertSet('step', 'done');

        Mail::assertNothingSent();
    }

    public function test_create_dispatches_contract_updated_on_success(): void
    {
        Storage::fake('local');
        config(['filesystems.documents_disk' => 'local']);

        [$organization, $user, $unit, $tenant] = $this->createOpenCreateGraph();
        $this->seedLandlord($organization->id);

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->dispatch('open-contract-create')
            ->set('unit_id', $unit->id)
            ->set('tenant_id', $tenant->id)
            ->set('rent_amount', '10000')
            ->set('deposit_amount', '10000')
            ->set('due_day', '5')
            ->set('grace_days', '3')
            ->set('penalty_rate_daily', '5')
            ->set('starts_at', '2026-08-01')
            ->set('ends_at', '2027-07-31')
            ->call('save')
            ->assertDispatched('contract-updated');
    }

    public function test_edit_requires_ends_at(): void
    {
        [$organization, $contract, $user] = $this->createContractGraph();
        $this->seedLandlord($organization->id);
        $contract->update(['ends_at' => null]);

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->dispatch('open-contract-edit', contractId: $contract->id)
            ->set('ends_at', null)
            ->call('save')
            ->assertHasErrors(['ends_at']);
    }

    public function test_edit_save_ignores_tampered_unit_and_tenant(): void
    {
        [$organization, $contract, $user] = $this->createContractGraph();
        $contractUnit = Unit::query()->withoutOrganizationScope()->findOrFail($contract->unit_id);

        $otherUnit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $contractUnit->property_id,
            'status' => 'active',
        ]);
        $otherTenant = Tenant::factory()->create([
            'organization_id' => $organization->id,
            'status' => 'active',
        ]);

        $originalUnitId = $contract->unit_id;
        $originalTenantId = $contract->tenant_id;

        Livewire::actingAs($user)
            ->test(CreateModal::class)
            ->dispatch('open-contract-edit', contractId: $contract->id)
            ->set('unit_id', $otherUnit->id)
            ->set('tenant_id', $otherTenant->id)
            ->set('rent_amount', '12500')
            ->call('save')
            ->assertSet('open', false);

        $contract->refresh();

        $this->assertSame($originalUnitId, $contract->unit_id);
        $this->assertSame($originalTenantId, $contract->tenant_id);
        $this->assertSame('12500.00', (string) $contract->rent_amount);
    }

    /**
     * @return array{Organization, Contract, User}
     */
    private function createContractGraph(): array
    {
        $organization = Organization::factory()->create();

        $property = Property::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'status' => 'active',
        ]);

        $tenant = Tenant::factory()->create([
            'organization_id' => $organization->id,
            'status' => 'active',
        ]);

        $this->seedLandlord($organization->id);

        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'rent_amount' => 10000,
            'penalty_rate_daily' => 0.05,
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-12-31',
        ]);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        return [$organization, $contract, $user];
    }

    private function seedLandlord(int $organizationId): void
    {
        TenantContext::setOrganizationId($organizationId);
        OrganizationSetting::query()
            ->withoutOrganizationScope()
            ->updateOrCreate(
                ['organization_id' => $organizationId],
                ['landlord_name' => 'Arrendador Demo S.A. de C.V.'],
            );
    }

    /**
     * @return array{0: Organization, 1: User, 2: Unit, 3: Tenant}
     */
    private function createOpenCreateGraph(): array
    {
        $organization = Organization::factory()->create();
        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'status' => 'active',
        ]);
        $tenant = Tenant::factory()->create([
            'organization_id' => $organization->id,
            'status' => 'active',
            'email' => 'tenant@example.com',
        ]);
        $user = User::factory()->create(['organization_id' => $organization->id]);

        return [$organization, $user, $unit, $tenant];
    }
}
