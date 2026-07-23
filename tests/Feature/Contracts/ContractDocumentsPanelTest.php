<?php

namespace Tests\Feature\Contracts;

use App\Livewire\Documents\Panel;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\ContractDocumentCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContractDocumentsPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploads_pdf_with_category(): void
    {
        Storage::fake('local');
        [$organization, $contract] = $this->createContractGraph();
        $user = User::factory()->create(['organization_id' => $organization->id]);

        Livewire::actingAs($user)
            ->test(Panel::class, [
                'documentableType' => Contract::class,
                'documentableId' => $contract->id,
                'variant' => 'contract',
            ])
            ->set('category', ContractDocumentCategory::Contract->value)
            ->set('document', UploadedFile::fake()->create('contrato.pdf', 100, 'application/pdf'))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('documents', [
            'documentable_type' => Contract::class,
            'documentable_id' => $contract->id,
            'category' => ContractDocumentCategory::Contract->value,
            'mime' => 'application/pdf',
        ]);
    }

    public function test_rejects_non_pdf_on_contract_variant(): void
    {
        Storage::fake('local');
        [$organization, $contract] = $this->createContractGraph();
        $user = User::factory()->create(['organization_id' => $organization->id]);

        Livewire::actingAs($user)
            ->test(Panel::class, [
                'documentableType' => Contract::class,
                'documentableId' => $contract->id,
                'variant' => 'contract',
            ])
            ->set('category', ContractDocumentCategory::Guarantor->value)
            ->set('document', UploadedFile::fake()->image('aval.jpg'))
            ->call('save')
            ->assertHasErrors(['document']);
    }

    public function test_rejects_duplicate_category(): void
    {
        Storage::fake('local');
        [$organization, $contract] = $this->createContractGraph();
        $user = User::factory()->create(['organization_id' => $organization->id]);

        Document::factory()->create([
            'organization_id' => $organization->id,
            'documentable_type' => Contract::class,
            'documentable_id' => $contract->id,
            'category' => ContractDocumentCategory::Contract->value,
            'type' => 'CONTRACT_DOCUMENT',
            'mime' => 'application/pdf',
            'path' => 'documents/contract/'.$organization->id.'/existing.pdf',
            'meta' => ['disk' => 'local'],
        ]);

        Livewire::actingAs($user)
            ->test(Panel::class, [
                'documentableType' => Contract::class,
                'documentableId' => $contract->id,
                'variant' => 'contract',
            ])
            ->set('category', ContractDocumentCategory::Contract->value)
            ->set('document', UploadedFile::fake()->create('otro.pdf', 100, 'application/pdf'))
            ->call('save')
            ->assertHasErrors(['category']);
    }

    public function test_delete_frees_category_for_reupload(): void
    {
        Storage::fake('local');
        [$organization, $contract] = $this->createContractGraph();
        $user = User::factory()->create(['organization_id' => $organization->id]);

        $path = 'documents/contract/'.$organization->id.'/contrato.pdf';
        Storage::disk('local')->put($path, 'pdf');

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

        Livewire::actingAs($user)
            ->test(Panel::class, [
                'documentableType' => Contract::class,
                'documentableId' => $contract->id,
                'variant' => 'contract',
            ])
            ->call('confirmDeleteDocument', $document->id)
            ->assertSet('showDeleteConfirm', true)
            ->call('executeDeleteDocumentConfirm')
            ->assertSet('showDeleteConfirm', false);

        $this->assertSoftDeleted('documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($path);

        Livewire::actingAs($user)
            ->test(Panel::class, [
                'documentableType' => Contract::class,
                'documentableId' => $contract->id,
                'variant' => 'contract',
            ])
            ->set('category', ContractDocumentCategory::Contract->value)
            ->set('document', UploadedFile::fake()->create('nuevo.pdf', 100, 'application/pdf'))
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_delete_requires_upload_permission(): void
    {
        Storage::fake('local');
        [$organization, $contract] = $this->createContractGraph();

        $role = Role::findOrCreate('ViewOnlyDocs', 'web');
        $role->syncPermissions(['dashboard.view', 'documents.view']);

        $user = User::factory()->create(['organization_id' => $organization->id]);
        $user->syncRoles(['ViewOnlyDocs']);

        $document = Document::factory()->create([
            'organization_id' => $organization->id,
            'documentable_type' => Contract::class,
            'documentable_id' => $contract->id,
            'category' => ContractDocumentCategory::Contract->value,
            'path' => 'documents/contract/'.$organization->id.'/x.pdf',
            'meta' => ['disk' => 'local'],
        ]);

        Livewire::actingAs($user)
            ->test(Panel::class, [
                'documentableType' => Contract::class,
                'documentableId' => $contract->id,
                'variant' => 'contract',
            ])
            ->call('confirmDeleteDocument', $document->id)
            ->assertStatus(403);
    }

    public function test_used_categories_hidden_from_select(): void
    {
        [$organization, $contract] = $this->createContractGraph();
        $user = User::factory()->create(['organization_id' => $organization->id]);

        Document::factory()->create([
            'organization_id' => $organization->id,
            'documentable_type' => Contract::class,
            'documentable_id' => $contract->id,
            'category' => ContractDocumentCategory::Contract->value,
        ]);

        Livewire::actingAs($user)
            ->test(Panel::class, [
                'documentableType' => Contract::class,
                'documentableId' => $contract->id,
                'variant' => 'contract',
            ])
            ->assertViewHas('availableCategories', fn (array $options): bool => ! array_key_exists('contract', $options)
                && array_key_exists('guarantor', $options));
    }

    /**
     * @return array{Organization, Contract}
     */
    private function createContractGraph(): array
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
        ]);

        return [$organization, $contract];
    }
}
