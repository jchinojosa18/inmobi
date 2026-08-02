<?php

namespace Tests\Feature\Documents;

use App\Livewire\Documents\Panel;
use App\Mail\ContractDocumentMail;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\ContractDocumentCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContractDocumentSharePanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_share_button_only_for_contract_category(): void
    {
        Storage::fake('local');
        [$user, $contract, $contractDoc, $idDoc] = $this->setupPanelWithTwoDocs();

        Livewire::actingAs($user)
            ->test(Panel::class, [
                'documentableType' => Contract::class,
                'documentableId' => $contract->id,
                'variant' => 'contract',
            ])
            ->assertSee(__('documents.share'))
            ->call('openShareModal', $contractDoc->id)
            ->assertSet('showShareModal', true)
            ->assertNotSet('shareUrl', null)
            ->call('closeShareModal')
            ->call('openShareModal', $idDoc->id)
            ->assertStatus(403);
    }

    public function test_send_email_delivers_to_tenant(): void
    {
        Mail::fake();
        Storage::fake('local');
        [$user, $contract, $contractDoc] = $this->setupPanelWithContractDoc(
            email: 'tenant@example.com',
            withReceiptsSend: true,
        );

        Livewire::actingAs($user)
            ->test(Panel::class, [
                'documentableType' => Contract::class,
                'documentableId' => $contract->id,
                'variant' => 'contract',
            ])
            ->call('openShareModal', $contractDoc->id)
            ->call('sendContractDocumentEmail')
            ->assertHasNoErrors();

        Mail::assertSent(ContractDocumentMail::class, fn (ContractDocumentMail $mail) => $mail->hasTo('tenant@example.com'));
    }

    public function test_send_email_requires_receipts_send_permission(): void
    {
        Mail::fake();
        Storage::fake('local');
        [$user, $contract, $contractDoc] = $this->setupPanelWithContractDoc(
            email: 'tenant@example.com',
            withReceiptsSend: false,
        );

        Livewire::actingAs($user)
            ->test(Panel::class, [
                'documentableType' => Contract::class,
                'documentableId' => $contract->id,
                'variant' => 'contract',
            ])
            ->call('openShareModal', $contractDoc->id)
            ->call('sendContractDocumentEmail')
            ->assertStatus(403);

        Mail::assertNothingSent();
    }

    public function test_send_email_without_tenant_email_shows_feedback(): void
    {
        Mail::fake();
        Storage::fake('local');
        [$user, $contract, $contractDoc] = $this->setupPanelWithContractDoc(
            email: null,
            withReceiptsSend: true,
        );

        Livewire::actingAs($user)
            ->test(Panel::class, [
                'documentableType' => Contract::class,
                'documentableId' => $contract->id,
                'variant' => 'contract',
            ])
            ->call('openShareModal', $contractDoc->id)
            ->call('sendContractDocumentEmail')
            ->assertSet('shareEmailFeedback', __('documents.no_tenant_email'));

        Mail::assertNothingSent();
    }

    public function test_whatsapp_url_uses_document_share_link(): void
    {
        Storage::fake('local');
        [$user, $contract, $contractDoc] = $this->setupPanelWithContractDoc(
            email: 'tenant@example.com',
            phone: '526641112233',
            withReceiptsSend: true,
        );

        $component = Livewire::actingAs($user)
            ->test(Panel::class, [
                'documentableType' => Contract::class,
                'documentableId' => $contract->id,
                'variant' => 'contract',
            ])
            ->call('openShareModal', $contractDoc->id);

        $whatsAppUrl = $component->get('whatsAppUrl');
        $shareUrl = $component->get('shareUrl');

        $this->assertIsString($whatsAppUrl);
        $this->assertStringContainsString('wa.me', $whatsAppUrl);
        $this->assertStringContainsString('/documents/'.$contractDoc->id.'/shared', (string) $shareUrl);
    }

    /**
     * @return array{0: User, 1: Contract, 2: Document, 3: Document}
     */
    private function setupPanelWithTwoDocs(): array
    {
        [$user, $contract, $contractDoc] = $this->setupPanelWithContractDoc(
            email: 'tenant@example.com',
            withReceiptsSend: true,
        );

        $idPath = 'documents/contract/'.$contract->organization_id.'/ine.pdf';
        Storage::disk('local')->put($idPath, '%PDF-1.4 ine');
        $idDoc = Document::factory()->create([
            'organization_id' => $contract->organization_id,
            'documentable_type' => Contract::class,
            'documentable_id' => $contract->id,
            'category' => ContractDocumentCategory::Id->value,
            'type' => 'CONTRACT_DOCUMENT',
            'mime' => 'application/pdf',
            'path' => $idPath,
            'meta' => ['disk' => 'local'],
        ]);

        return [$user, $contract, $contractDoc, $idDoc];
    }

    /**
     * @return array{0: User, 1: Contract, 2: Document}
     */
    private function setupPanelWithContractDoc(
        ?string $email,
        ?string $phone = null,
        bool $withReceiptsSend = false,
    ): array {
        $organization = Organization::factory()->create();
        $roleName = 'ContractDocShareTester';
        $role = Role::findOrCreate($roleName, 'web');
        $permissions = ['documents.view'];
        if ($withReceiptsSend) {
            $permissions[] = 'receipts.send';
        }
        $role->syncPermissions($permissions);

        $user = User::factory()->create(['organization_id' => $organization->id]);
        $user->syncRoles([$roleName]);

        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $tenant = Tenant::factory()->create([
            'organization_id' => $organization->id,
            'email' => $email,
            'phone' => $phone,
        ]);
        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
        ]);

        $path = 'documents/contract/'.$organization->id.'/contrato.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 contract-bytes');
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

        return [$user, $contract, $document];
    }
}
