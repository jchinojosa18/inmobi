# Contract Document Share Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** From the contract documents list, let staff resend the stored «Contrato» PDF via email, WhatsApp, or a signed shareable link.

**Architecture:** Extend `Documents\Panel` with a share modal. Introduce `DocumentShareUrl` + public signed route that streams the stored file. Introduce `ContractDocumentMail` that attaches Storage bytes (not regenerated DomPDF). Reuse org contract email/WhatsApp templates with `shared_contract_url` pointing at the document share URL. Keep `contracts.agreement.share` unchanged for renewal.

**Tech Stack:** Laravel 11, Livewire 4, Blade, Tailwind, Spatie permissions, PHPUnit via Sail, Pint.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-08-01-contract-document-share-design.md`.
- Always run via `./vendor/bin/sail` (artisan, composer, npm, tests, pint).
- Diff mínimo: only share flow for category `contract` on contract document panel.
- Shared content = stored `Document` file; never regenerate via `GenerateLeaseAgreementPdfAction` in this flow.
- Permissions: modal/`documents.view`; email/`receipts.send`.
- Signed URL TTL: 7 days; relative signatures (`signed:relative`) like payment receipts.
- Bind `TenantContext` to the document’s `organization_id` inside mail/share data builders when loading scoped relations (same class of bug as payment receipt WhatsApp PDF).
- No commit unless the user explicitly asks.
- Tests: `./vendor/bin/sail test --filter=<TestName>`; format: `./vendor/bin/sail pint --dirty`.

---

## File Map

| File | Responsibility |
|------|----------------|
| `app/Support/DocumentShareUrl.php` | Build 7-day relative signed URL for `documents.shared` |
| `app/Http/Controllers/Documents/SharedDownloadController.php` | Stream stored contract-category document for signed guests |
| `routes/web.php` | Register `GET /documents/{documentId}/shared` |
| `app/Mail/ContractDocumentMail.php` | Email with template + Storage PDF attach |
| `resources/views/emails/contract-document.blade.php` | Email HTML (mirror `contract-agreement`) |
| `app/Livewire/Documents/Panel.php` | Share modal state + send email + WhatsApp URL |
| `resources/views/livewire/documents/panel.blade.php` | Share button + modal UI |
| `lang/es/documents.php` / `lang/en/documents.php` | Share UI strings |
| `tests/Feature/Documents/DocumentShareUrlTest.php` | Signed share route behavior |
| `tests/Feature/Documents/ContractDocumentSharePanelTest.php` | Panel button, email, WhatsApp |

---

### Task 1: Signed document share URL + controller

**Files:**
- Create: `app/Support/DocumentShareUrl.php`
- Create: `app/Http/Controllers/Documents/SharedDownloadController.php`
- Modify: `routes/web.php` (near other signed share routes ~229)
- Create: `tests/Feature/Documents/DocumentShareUrlTest.php`

**Interfaces:**
- Consumes: `Document` model, `Contract::class`, `ContractDocumentCategory::Contract`, Storage disk from `meta.disk`
- Produces:
  - `DocumentShareUrl::make(int $documentId, ?DateTimeInterface $expiresAt = null): string`
  - Route name `documents.shared`
  - `SharedDownloadController::__invoke(Request $request, int $documentId): StreamedResponse`

- [ ] **Step 1: Write failing feature tests**

Create `tests/Feature/Documents/DocumentShareUrlTest.php`:

```php
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

        $this->get($pathWithQuery)
            ->assertOk()
            ->assertHeader('content-disposition', 'inline')
            ->assertSee('shared-contract', false);
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/sail test --filter=DocumentShareUrlTest`

Expected: FAIL (class `DocumentShareUrl` / route missing).

- [ ] **Step 3: Implement URL helper, controller, route**

`app/Support/DocumentShareUrl.php`:

```php
<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Facades\URL;

final class DocumentShareUrl
{
    public static function make(int $documentId, ?DateTimeInterface $expiresAt = null): string
    {
        $relative = URL::temporarySignedRoute(
            'documents.shared',
            $expiresAt ?? now()->addDays(7),
            ['documentId' => $documentId],
            absolute: false,
        );

        return URL::to($relative);
    }
}
```

`app/Http/Controllers/Documents/SharedDownloadController.php`:

```php
<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Document;
use App\Support\ContractDocumentCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SharedDownloadController extends Controller
{
    public function __invoke(Request $request, int $documentId): StreamedResponse
    {
        $document = Document::query()
            ->withoutOrganizationScope()
            ->findOrFail($documentId);

        if (
            $document->documentable_type !== Contract::class
            || $document->category !== ContractDocumentCategory::Contract
        ) {
            abort(404);
        }

        $disk = (string) data_get($document->meta, 'disk', config('filesystems.documents_disk', 'local'));

        if (! Storage::disk($disk)->exists($document->path)) {
            abort(404);
        }

        return Storage::disk($disk)->response($document->path, null, [
            'Content-Type' => $document->mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline',
        ]);
    }
}
```

In `routes/web.php`, next to `payments.receipt.share` / `contracts.agreement.share`:

```php
use App\Http\Controllers\Documents\SharedDownloadController;

Route::get('/documents/{documentId}/shared', SharedDownloadController::class)
    ->middleware('signed:relative')
    ->name('documents.shared');
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/sail test --filter=DocumentShareUrlTest`

Expected: PASS

- [ ] **Step 5: Format**

Run: `./vendor/bin/sail pint --dirty`

---

### Task 2: `ContractDocumentMail` (stored PDF attach)

**Files:**
- Create: `app/Mail/ContractDocumentMail.php`
- Create: `resources/views/emails/contract-document.blade.php`
- Create: `tests/Feature/Documents/ContractDocumentMailTest.php`

**Interfaces:**
- Consumes: `Document`, `DocumentShareUrl::make`, `OrganizationSettingsService`, `OrganizationMailSender`, Storage
- Produces: `ContractDocumentMail` constructor `public Document $document`; attaches stored file bytes

- [ ] **Step 1: Write failing mail test**

```php
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
use Illuminate\Support\Facades\Mail;
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
        OrganizationSetting::query()->updateOrCreate(
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
```

For `OrganizationSetting`, use the same `withoutOrganizationScope()->updateOrCreate` pattern as `ContractAgreementSendTest::createRenewableSource` (only `landlord_name` is required for settings seed; email template can rely on service defaults or set `contract_email_template` explicitly).

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail test --filter=ContractDocumentMailTest`

Expected: FAIL (`ContractDocumentMail` missing).

- [ ] **Step 3: Implement mailable + blade**

`resources/views/emails/contract-document.blade.php` — copy structure from `resources/views/emails/contract-agreement.blade.php` (same `$organizationName`, `$messageBody`, `$shareUrl`, `$contract` variables).

`app/Mail/ContractDocumentMail.php`:

```php
<?php

namespace App\Mail;

use App\Models\Contract;
use App\Models\Document;
use App\Support\ContractDocumentCategory;
use App\Support\DateDisplay;
use App\Support\DocumentShareUrl;
use App\Support\OrganizationMailSender;
use App\Support\OrganizationSettingsService;
use App\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ContractDocumentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Document $document) {}

    public function envelope(): Envelope
    {
        [$contract, $organizationName, $unitName] = $this->resolveContext();

        return new Envelope(
            from: OrganizationMailSender::fromAddress($organizationName),
            subject: 'Contrato de arrendamiento'.($unitName !== '' ? ' — '.$unitName : ''),
        );
    }

    public function content(): Content
    {
        [$contract, $organizationName, $unitName] = $this->resolveContext();
        $shareUrl = DocumentShareUrl::make((int) $this->document->id);
        $settingsService = app(OrganizationSettingsService::class);
        $settings = $settingsService->forOrganization((int) $this->document->organization_id);

        $messageBody = $settingsService->renderTemplate(
            (string) $settings['contract_email_template'],
            [
                'tenant_name' => (string) ($contract->tenant?->full_name ?? 'cliente'),
                'unit_name' => $unitName !== '' ? $unitName : 'unidad',
                'shared_contract_url' => $shareUrl,
                'rent_amount' => number_format((float) $contract->rent_amount, 2, '.', ''),
                'starts_at' => DateDisplay::formatDate($contract->starts_at),
                'ends_at' => DateDisplay::formatDate($contract->ends_at),
            ]
        );

        return new Content(
            view: 'emails.contract-document',
            with: [
                'organizationName' => $organizationName,
                'contract' => $contract,
                'shareUrl' => $shareUrl,
                'messageBody' => $messageBody,
            ],
        );
    }

    public function build(): self
    {
        $document = Document::query()
            ->withoutOrganizationScope()
            ->findOrFail($this->document->id);

        if (
            $document->documentable_type !== Contract::class
            || $document->category !== ContractDocumentCategory::Contract
        ) {
            throw ValidationException::withMessages([
                'document' => 'Documento de contrato inválido.',
            ]);
        }

        $disk = (string) data_get($document->meta, 'disk', config('filesystems.documents_disk', 'local'));
        if (! Storage::disk($disk)->exists($document->path)) {
            throw ValidationException::withMessages([
                'document' => 'Archivo de contrato no encontrado.',
            ]);
        }

        $pdfContent = Storage::disk($disk)->get($document->path);

        return $this->attachData(
            $pdfContent,
            'contrato-'.$document->id.'.pdf',
            ['mime' => $document->mime ?: 'application/pdf']
        );
    }

    /**
     * @return array{0: Contract, 1: string, 2: string}
     */
    private function resolveContext(): array
    {
        $previous = TenantContext::currentOrganizationId();
        TenantContext::setOrganizationId((int) $this->document->organization_id);

        try {
            $document = Document::query()
                ->withoutOrganizationScope()
                ->findOrFail($this->document->id);

            /** @var Contract $contract */
            $contract = Contract::query()
                ->withoutOrganizationScope()
                ->with(['tenant', 'unit.property', 'organization'])
                ->findOrFail($document->documentable_id);

            $organizationName = (string) ($contract->organization?->name ?? '');
            $unitName = trim((string) ($contract->unit?->property?->name.' / '.$contract->unit?->name));

            return [$contract, $organizationName, $unitName];
        } finally {
            TenantContext::setOrganizationId($previous);
        }
    }
}
```

Adjust attachment assertion in the test to match how Laravel exposes `rawAttachments` after `build()` in this project (mirror `PaymentReceiptMail` / existing mail tests if present).

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/sail test --filter=ContractDocumentMailTest`

Expected: PASS

- [ ] **Step 5: Format**

Run: `./vendor/bin/sail pint --dirty`

---

### Task 3: Panel share modal (UI + Livewire + i18n)

**Files:**
- Modify: `lang/es/documents.php`
- Modify: `lang/en/documents.php`
- Modify: `app/Livewire/Documents/Panel.php`
- Modify: `resources/views/livewire/documents/panel.blade.php`
- Create: `tests/Feature/Documents/ContractDocumentSharePanelTest.php`

**Interfaces:**
- Consumes: `DocumentShareUrl::make`, `ContractDocumentMail`, `OrganizationSettingsService`, `ContractDocumentCategory::Contract`
- Produces on `Panel`:
  - `bool $showShareModal`
  - `?int $sharingDocumentId`
  - `?string $shareUrl`
  - `?string $whatsAppUrl`
  - `?string $shareTenantEmail`
  - `?string $shareEmailFeedback` (`success`|`error` message text)
  - `openShareModal(int $documentId): void`
  - `closeShareModal(): void`
  - `sendContractDocumentEmail(): void`
  - Render also passes `canSendReceipts` (`receipts.send`)

- [ ] **Step 1: Add i18n keys**

In `lang/es/documents.php`:

```php
'share' => 'Compartir',
'share_title' => 'Compartir contrato',
'share_description' => 'Envía el PDF guardado por correo, WhatsApp o copia el enlace temporal.',
'send_email' => 'Enviar por email',
'open_whatsapp' => 'Abrir WhatsApp',
'copy_link' => 'Copiar link',
'copied' => 'Copiado',
'shareable_link' => 'Enlace compartible',
'email_recipient' => 'Destinatario',
'no_tenant_email' => 'El inquilino no tiene email registrado.',
'email_sent' => 'Mensaje enviado',
'email_failed' => 'No se pudo enviar el correo.',
'file_missing' => 'El archivo del contrato no está disponible.',
```

Mirror English equivalents in `lang/en/documents.php`.

- [ ] **Step 2: Write failing panel tests**

Create `tests/Feature/Documents/ContractDocumentSharePanelTest.php`:

```php
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
use Spatie\Permission\Models\Permission;
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

    public function test_send_email_requires_receipts_send_and_tenant_email(): void
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
        Role::findOrCreate('Admin', 'web');
        Permission::findOrCreate('documents.view', 'web');
        Permission::findOrCreate('receipts.send', 'web');

        $user = User::factory()->create(['organization_id' => $organization->id]);
        $user->givePermissionTo('documents.view');
        if ($withReceiptsSend) {
            $user->givePermissionTo('receipts.send');
        }

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
```

Livewire abort assertion: use `->assertStatus(403)` (same as `ContractDocumentsPanelTest`).

- [ ] **Step 3: Run tests to verify they fail**

Run: `./vendor/bin/sail test --filter=ContractDocumentSharePanelTest`

Expected: FAIL (methods / strings missing).

- [ ] **Step 4: Implement Panel methods**

Add public properties and methods to `app/Livewire/Documents/Panel.php`:

```php
public bool $showShareModal = false;
public ?int $sharingDocumentId = null;
public ?string $shareUrl = null;
public ?string $whatsAppUrl = null;
public ?string $shareTenantEmail = null;
public ?string $shareEmailFeedback = null;

public function openShareModal(int $documentId): void
{
    $document = $this->findShareableContractDocument($documentId);
    $contract = $this->resolveDocumentable();
    $contract->loadMissing(['tenant', 'unit.property']);

    $this->sharingDocumentId = $document->id;
    $this->shareUrl = DocumentShareUrl::make($document->id);
    $this->shareTenantEmail = $contract->tenant?->email;
    $this->shareEmailFeedback = null;
    $this->whatsAppUrl = $this->buildContractDocumentWhatsAppUrl($contract, $this->shareUrl);
    $this->showShareModal = true;
}

public function closeShareModal(): void
{
    $this->showShareModal = false;
    $this->sharingDocumentId = null;
    $this->shareUrl = null;
    $this->whatsAppUrl = null;
    $this->shareTenantEmail = null;
    $this->shareEmailFeedback = null;
}

public function sendContractDocumentEmail(): void
{
    if (! (auth()->user()?->can('receipts.send') ?? false)) {
        abort(403);
    }

    if ($this->sharingDocumentId === null) {
        return;
    }

    $document = $this->findShareableContractDocument($this->sharingDocumentId);
    $contract = $this->resolveDocumentable();
    $contract->loadMissing(['tenant']);
    $email = $contract->tenant?->email;

    if (! is_string($email) || $email === '') {
        $this->shareEmailFeedback = __('documents.no_tenant_email');

        return;
    }

    $disk = (string) data_get($document->meta, 'disk', config('filesystems.documents_disk', 'local'));
    if (! Storage::disk($disk)->exists($document->path)) {
        $this->shareEmailFeedback = __('documents.file_missing');

        return;
    }

    try {
        Mail::to($email)->send(new ContractDocumentMail($document));
        $this->shareEmailFeedback = __('documents.email_sent');
    } catch (\Throwable) {
        $this->shareEmailFeedback = __('documents.email_failed');
    }
}

private function findShareableContractDocument(int $documentId): Document
{
    if (! $this->isContractVariant()) {
        abort(404);
    }

    $document = Document::query()
        ->where('documentable_type', Contract::class)
        ->where('documentable_id', $this->documentableId)
        ->findOrFail($documentId);

    if ($document->category !== ContractDocumentCategory::Contract) {
        abort(403);
    }

    return $document;
}

private function buildContractDocumentWhatsAppUrl(Contract $contract, string $shareUrl): string
{
    $settingsService = app(OrganizationSettingsService::class);
    $settings = $settingsService->forOrganization((int) $contract->organization_id);
    $unitName = trim((string) ($contract->unit?->property?->name.' / '.$contract->unit?->name));
    $phone = preg_replace('/\D+/', '', (string) $contract->tenant?->phone) ?: null;

    $message = $settingsService->renderTemplate(
        (string) $settings['contract_whatsapp_template'],
        [
            'tenant_name' => (string) ($contract->tenant?->full_name ?? 'cliente'),
            'unit_name' => $unitName !== '' ? $unitName : 'unidad',
            'shared_contract_url' => $shareUrl,
            'rent_amount' => number_format((float) $contract->rent_amount, 2, '.', ''),
            'starts_at' => DateDisplay::formatDate($contract->starts_at),
            'ends_at' => DateDisplay::formatDate($contract->ends_at),
        ]
    );

    $encoded = rawurlencode($message);

    return $phone !== null
        ? "https://wa.me/{$phone}?text={$encoded}"
        : "https://wa.me/?text={$encoded}";
}
```

Pass to the view in `render()`:

```php
'canSendReceipts' => auth()->user()?->can('receipts.send') ?? false,
```

Add imports: `ContractDocumentMail`, `DocumentShareUrl`, `OrganizationSettingsService`, `DateDisplay`, `Mail` facade.

- [ ] **Step 5: Implement Blade modal**

In contract variant actions cell (next to view/delete), when `$item['category'] === 'contract'`:

```blade
<x-ui.button
    type="button"
    variant="secondary"
    size="sm"
    wire:click="openShareModal({{ $item['id'] }})"
>
    {{ __('documents.share') }}
</x-ui.button>
```

Add modal (pattern from upload modal / renew done + payments share):

```blade
@if ($showShareModal)
    <x-ui.modal
        :open="true"
        :title="__('documents.share_title')"
        :aria-label="__('documents.share_title')"
        max-width="md"
        close-action="closeShareModal"
    >
        <p class="text-sm text-slate-600">{{ __('documents.share_description') }}</p>

        <div class="mt-4 space-y-4">
            @if ($canSendReceipts)
                <div>
                    <x-ui.input
                        id="contract-doc-email"
                        :label="__('documents.email_recipient')"
                        type="email"
                        :value="$shareTenantEmail"
                        disabled
                    />
                    @if ($shareTenantEmail)
                        <x-ui.button
                            type="button"
                            class="mt-3"
                            wire:click="sendContractDocumentEmail"
                            wire:loading.attr="disabled"
                        >
                            {{ __('documents.send_email') }}
                        </x-ui.button>
                    @else
                        <p class="mt-2 text-sm text-amber-700">{{ __('documents.no_tenant_email') }}</p>
                    @endif
                    @if ($shareEmailFeedback)
                        <p class="mt-2 text-sm text-slate-700">{{ $shareEmailFeedback }}</p>
                    @endif
                </div>
            @endif

            <div class="flex flex-wrap gap-2">
                @if ($shareUrl)
                    <button
                        type="button"
                        x-data="{ copied: false }"
                        x-on:click="navigator.clipboard.writeText(@js($shareUrl)); copied = true; setTimeout(() => copied = false, 2000)"
                        class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        <span x-text="copied ? @js(__('documents.copied')) : @js(__('documents.copy_link'))"></span>
                    </button>
                @endif

                @if ($whatsAppUrl)
                    <a
                        href="{{ $whatsAppUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 rounded-md border border-emerald-300 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50"
                    >
                        {{ __('documents.open_whatsapp') }}
                    </a>
                @endif
            </div>

            @if ($shareUrl)
                <div>
                    <p class="mb-1.5 text-xs font-medium uppercase tracking-wide text-slate-500">
                        {{ __('documents.shareable_link') }}
                    </p>
                    <textarea readonly rows="3" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs">{{ $shareUrl }}</textarea>
                </div>
            @endif
        </div>
    </x-ui.modal>
@endif
```

Match existing `x-ui.button` / modal styling used in this blade; keep visual consistent with renew/payment share CTAs.

- [ ] **Step 6: Run panel tests**

Run: `./vendor/bin/sail test --filter=ContractDocumentSharePanelTest`

Expected: PASS

- [ ] **Step 7: Format**

Run: `./vendor/bin/sail pint --dirty`

---

### Task 4: Full verification

**Files:** none new

- [ ] **Step 1: Run all related tests**

```bash
./vendor/bin/sail test --filter='DocumentShareUrlTest|ContractDocumentMailTest|ContractDocumentSharePanelTest|ContractDocumentsPanelTest|DocumentSecurityTest|ContractAgreementSendTest'
```

Expected: all PASS

- [ ] **Step 2: Pint**

```bash
./vendor/bin/sail pint --dirty
```

- [ ] **Step 3: Manual smoke (optional)**

1. Open a contract with a category-Contrato document.
2. Click Compartir → copy link → open in private window → PDF shows.
3. Send email (Mailpit) → attachment is stored file.
4. WhatsApp link opens with message containing share URL.
5. Confirm INE/other categories have no Compartir button.

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Share only category Contrato | Task 3 |
| Stored file (not regenerated) | Tasks 1–2 |
| Modal: email / WhatsApp / copy link | Task 3 |
| Immediate email to tenant | Task 3 |
| `receipts.send` gate | Task 3 |
| Signed 7-day relative URL | Task 1 |
| Guest access via signature | Task 1 |
| Reject wrong category on share route | Task 1 |
| Keep renewal `contracts.agreement.share` | Out of scope / untouched |
| TenantContext-safe mail context | Task 2 |
| Tests listed in spec | Tasks 1–3 |
