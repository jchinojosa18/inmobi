# Contract Create/Edit PDF Generation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** On contract create, generate/store the lease PDF, offer send-email checkbox, and show a RenewWizard-style done step; on edit, regenerate and replace the category-`contract` Document.

**Architecture:** Extend `CreateModal::save()` to assert `landlord_name`, require `ends_at`, call `GenerateLeaseAgreementPdfAction` after the DB transaction, optionally send `ContractAgreementMail` on create, and switch create UX to a `done` step (`ContractAgreementShareUrl` + WhatsApp). On edit, delete the previous category-`contract` Document then regenerate. No new Action class in v1.

**Tech Stack:** Laravel 11, Livewire 4, DomPDF via `GenerateLeaseAgreementPdfAction`, Mail, PHPUnit via Sail, Pint.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-08-01-contract-create-pdf-design.md`.
- Always run via `./vendor/bin/sail` (tests, pint).
- Diff mínimo: `CreateModal` + blade + i18n + create-modal tests (and minimal helper updates).
- Reuse: `GenerateLeaseAgreementPdfAction`, `ContractAgreementMail`, `ContractAgreementShareUrl`, RenewWizard done/WhatsApp patterns.
- Do not change renewal flow or `documents.shared`.
- Create done uses regenerated share URL (`ContractAgreementShareUrl`), not `DocumentShareUrl`.
- No commit unless the user explicitly asks (during SDD execution, plan commit steps apply once user chose to execute).
- Tests: `./vendor/bin/sail test --filter=ContractCreateModalTest` (plus related PDF tests if needed); `./vendor/bin/sail pint --dirty`.

---

## File Map

| File | Responsibility |
|------|----------------|
| `app/Livewire/Contracts/CreateModal.php` | ends_at rules, landlord gate, PDF generate/replace, send_email, done step state |
| `resources/views/livewire/contracts/create-modal.blade.php` | Checkbox (create) + done UI |
| `lang/es/contracts.php` / `lang/en/contracts.php` | Create-success / send_email / ends_at_required strings |
| `tests/Feature/Contracts/ContractCreateModalTest.php` | PDF, email, validation, edit replace; seed landlord + ends_at in helpers |

---

### Task 1: Create generates PDF Document + validation gates

**Files:**
- Modify: `app/Livewire/Contracts/CreateModal.php`
- Modify: `tests/Feature/Contracts/ContractCreateModalTest.php`
- Modify: `lang/es/contracts.php`, `lang/en/contracts.php` (validation messages as needed)

**Interfaces:**
- Consumes: `GenerateLeaseAgreementPdfAction::execute(Contract $contract, ?int $userId): Document`, `OrganizationSettingsService::forOrganization`
- Produces: After successful **create** TX, a Document with `category=contract` on the new contract; `ends_at` required for create; missing `landlord_name` blocks save

- [ ] **Step 1: Write failing tests**

In `ContractCreateModalTest`, add helpers to seed landlord settings and free units, then:

```php
use App\Models\Document;
use App\Models\OrganizationSetting;
use App\Support\ContractDocumentCategory;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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

public function test_create_requires_landlord_name(): void
{
    Storage::fake('local');
    [$organization, $user, $unit, $tenant] = $this->createOpenCreateGraph();
    // no seedLandlord

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
        ->assertHasErrors(['landlord_name']); // or create_general — match implementation key

    $this->assertSame(0, Contract::query()->withoutOrganizationScope()->where('unit_id', $unit->id)->count());
}
```

Update `createContractGraph()` used by edit tests: set `ends_at` on the factory contract and call `seedLandlord`, so later edit+PDF work does not break existing tests when Task 3 lands. For Task 1, at minimum ensure create helpers exist; if you change `ends_at` rules for **both** create and edit in Task 1, fix all existing edit tests in this step.

Recommended for Task 1: make `ends_at` **required always** in `rules()` (create + edit), matching the approved spec.

Helper sketches:

```php
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

/** @return array{0: Organization, 1: User, 2: Unit, 3: Tenant} */
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
    // Grant contracts.manage if your User factory does not already (Admin role / permission).
    return [$organization, $user, $unit, $tenant];
}
```

Mirror permission seeding from `ContractDocumentsPanelTest` / Admin role as used elsewhere.

- [ ] **Step 2: Run tests — expect FAIL**

```bash
./vendor/bin/sail test --filter='ContractCreateModalTest::test_create_generates|ContractCreateModalTest::test_create_requires'
```

- [ ] **Step 3: Implement CreateModal gates + PDF on create**

In `CreateModal.php`:

1. Inject/resolve `GenerateLeaseAgreementPdfAction` and `OrganizationSettingsService` in `save()` (method injection OK).
2. Before TX: `assertLandlordNameConfigured` (private method copying RenewContractAction message / ValidationException → `addError('landlord_name', ...)`).
3. Change rules: `'ends_at' => ['required', 'date', 'after_or_equal:starts_at']` + message `ends_at.required`.
4. After successful TX + audit, call:

```php
try {
    app(GenerateLeaseAgreementPdfAction::class)->execute($contract->fresh(), auth()->id());
} catch (ValidationException $e) {
    $this->addError('landlord_name', $e->errors()['landlord_name'][0] ?? __('contracts.validation.renew_failed'));
    // Contract already exists — keep behavior aligned with renewal post-TX trade-off; do not delete contract.
    return null;
}
```

For Task 1 only, keep existing create redirect / edit close behavior temporarily (Task 2 switches create to done).

5. Add i18n `contracts.validation.ends_at_required` ES/EN.

- [ ] **Step 4: Run tests — expect PASS**

```bash
./vendor/bin/sail test --filter=ContractCreateModalTest
```

Fix any existing edit tests that fail due to required `ends_at` / landlord (set `ends_at` on factory create + `seedLandlord` in `createContractGraph`).

- [ ] **Step 5: Pint**

```bash
./vendor/bin/sail pint --dirty
```

- [ ] **Step 6: Commit** (when executing under SDD / user-approved execution)

```bash
git add app/Livewire/Contracts/CreateModal.php tests/Feature/Contracts/ContractCreateModalTest.php lang/es/contracts.php lang/en/contracts.php
git commit -m "$(cat <<'EOF'
Generate lease PDF when creating a contract.

Require ends_at and landlord_name so CreateModal can store a category-contract Document after save.
EOF
)"
```

---

### Task 2: Create send_email checkbox + done step (no immediate redirect)

**Files:**
- Modify: `app/Livewire/Contracts/CreateModal.php`
- Modify: `resources/views/livewire/contracts/create-modal.blade.php`
- Modify: `lang/es/contracts.php`, `lang/en/contracts.php`
- Modify: `tests/Feature/Contracts/ContractCreateModalTest.php`

**Interfaces:**
- Consumes: `ContractAgreementMail`, `ContractAgreementShareUrl::make`, WhatsApp builder pattern from `RenewWizard::buildContractWhatsAppUrl`
- Produces on create success:
  - `string $step` (`form`|`done`)
  - `bool $send_email`
  - `?string $pdfUrl`, `$shareUrl`, `$whatsAppUrl`
  - `?int $createdContractId`
  - No `redirect()->route('contracts.show')` on create

- [ ] **Step 1: Write failing tests**

```php
use App\Mail\ContractAgreementMail;
use Illuminate\Support\Facades\Mail;

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
```

- [ ] **Step 2: Run — expect FAIL**

```bash
./vendor/bin/sail test --filter='ContractCreateModalTest::test_create_send_email|ContractCreateModalTest::test_create_done'
```

- [ ] **Step 3: Implement state + save branch + blade**

`CreateModal` properties:

```php
public string $step = 'form';
public bool $send_email = false;
public ?string $pdfUrl = null;
public ?string $shareUrl = null;
public ?string $whatsAppUrl = null;
public ?int $createdContractId = null;
public ?string $tenantName = null;
public ?string $unitLabel = null;
```

On `open()`: after reset, if tenant later selected — default `send_email` when opening is tricky (tenant not chosen yet). Mirror RenewWizard by computing default in `save` gate and/or when `tenant_id` updates:

Simplest v1: in `render`/checkbox visibility, default `send_email` to true on first open when user has `receipts.send`, and only show checkbox when selected tenant has email (wire:model tenant_id → computed `canOfferSendEmail`). On `open()`, set `$this->send_email = auth()->user()?->can('receipts.send') ?? false`.

After create PDF success:

```php
$sendEmail = $this->send_email && (auth()->user()?->can('receipts.send') ?? false);
if ($sendEmail) {
    try {
        $contract->loadMissing('tenant');
        $email = $contract->tenant?->email;
        if (is_string($email) && $email !== '') {
            Mail::to($email)->send(new ContractAgreementMail($contract));
        }
    } catch (\Throwable $e) {
        report($e);
        // do not fail create
    }
}

$contract = $contract->fresh(['tenant', 'unit.property']);
$this->createdContractId = $contract->id;
$this->pdfUrl = route('contracts.agreement.pdf', ['contractId' => $contract->id]);
$this->shareUrl = ContractAgreementShareUrl::make($contract->id);
$this->whatsAppUrl = $this->buildContractWhatsAppUrl($contract, $this->shareUrl, app(OrganizationSettingsService::class));
$this->tenantName = (string) ($contract->tenant?->full_name ?? '');
$this->unitLabel = trim((string) ($contract->unit?->property?->name.' / '.$contract->unit?->name));
$this->step = 'done';
session()->flash('success', __('contracts.flash.contract_created'));
$this->dispatch('contract-created'); // if listeners exist; else omit
// DO NOT close() or redirect on create
return null;
```

Copy `buildContractWhatsAppUrl` from `RenewWizard` (private method). Prefer `wa.me/?text=` when phone empty (payments pattern) OR return null like RenewWizard — **match RenewWizard** (null without phone) for consistency with renewal done UI (`@if ($whatsAppUrl)`).

Blade: wrap current form in `@if ($step === 'form')` … `@else` done block cloned from `renew-wizard.blade.php` done section, with strings:
- `contracts.create_success_title` (new) — ES: «Contrato creado correctamente»
- Reuse `view_contract_pdf`, `shareable_link`, `view_detail`, WhatsApp

Form checkbox (create only, `@if (! $isEdit && $canSendReceipts)`):

```blade
@if (! $isEdit && $canSendReceipts)
    <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" wire:model="send_email" class="rounded border-slate-300" />
        {{ __('contracts.send_agreement_email') }}
    </label>
@endif
```

Pass `canSendReceipts` from `render()`: user can `receipts.send`. Optionally disable checkbox when selected tenant has no email (compute from `$tenants` collection).

Reset `step`, share fields in `resetForm()`.

- [ ] **Step 4: Run create-modal tests**

```bash
./vendor/bin/sail test --filter=ContractCreateModalTest
```

- [ ] **Step 5: Pint + commit**

```bash
./vendor/bin/sail pint --dirty
git commit -m "$(cat <<'EOF'
Add create-contract done step with email and share links.

After PDF generation, optionally email the agreement and keep the modal on a RenewWizard-style success step.
EOF
)"
```

---

### Task 3: Edit regenerates/replaces contract Document

**Files:**
- Modify: `app/Livewire/Contracts/CreateModal.php`
- Modify: `tests/Feature/Contracts/ContractCreateModalTest.php`

**Interfaces:**
- Consumes: Document query by contract + `ContractDocumentCategory::Contract`, Storage delete, `GenerateLeaseAgreementPdfAction::execute`
- Produces: After edit save, exactly one category-`contract` Document for that contract (new generation)

- [ ] **Step 1: Write failing tests**

```php
public function test_edit_replaces_contract_category_document(): void
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
        'meta' => ['disk' => 'local'],
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

    $remaining = Document::query()->withoutOrganizationScope()
        ->where('documentable_type', Contract::class)
        ->where('documentable_id', $contract->id)
        ->where('category', ContractDocumentCategory::Contract->value)
        ->get();

    $this->assertCount(1, $remaining);
    $this->assertNotSame($old->id, $remaining->first()->id);
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
```

Ensure `createContractGraph` sets `ends_at` (e.g. `2027-07-31`) and seeds landlord so other edit tests keep passing.

- [ ] **Step 2: Run — expect FAIL**

```bash
./vendor/bin/sail test --filter='ContractCreateModalTest::test_edit_replaces|ContractCreateModalTest::test_edit_requires_ends_at'
```

- [ ] **Step 3: Implement replace-on-edit**

After edit TX + audit, before flash/close:

```php
$this->replaceContractAgreementDocument($contract);
```

```php
private function replaceContractAgreementDocument(Contract $contract): void
{
    $existing = Document::query()
        ->where('documentable_type', Contract::class)
        ->where('documentable_id', $contract->id)
        ->where('category', ContractDocumentCategory::Contract)
        ->get();

    foreach ($existing as $document) {
        $disk = (string) data_get($document->meta, 'disk', config('filesystems.documents_disk', 'local'));
        if (Storage::disk($disk)->exists($document->path)) {
            Storage::disk($disk)->delete($document->path);
        }
        $document->update(['category' => null]); // frees unique category if constrained
        $document->delete();
    }

    app(GenerateLeaseAgreementPdfAction::class)->execute($contract->fresh(), auth()->id());
}
```

Mirror `Documents\Panel::deleteDocument` category-null-before-delete if that pattern is required by DB uniqueness.

Edit path: keep `close()` + flash + `contract-updated` (no done step).

Create path already generates PDF in Task 1 — do **not** call replace on create (no prior doc).

- [ ] **Step 4: Full CreateModal test file green**

```bash
./vendor/bin/sail test --filter=ContractCreateModalTest
```

- [ ] **Step 5: Pint + commit**

```bash
./vendor/bin/sail pint --dirty
git commit -m "$(cat <<'EOF'
Regenerate lease PDF when editing a contract.

Replace the existing category-contract Document so the stored agreement matches the updated terms.
EOF
)"
```

---

### Task 4: Full verification

**Files:** none new

- [ ] **Step 1: Run related suites**

```bash
./vendor/bin/sail test --filter='ContractCreateModalTest|LeaseAgreementPdfTest|ContractAgreementSendTest|RenewWizardTest|ContractDocumentsPanelTest'
```

Expected: all PASS

- [ ] **Step 2: Pint**

```bash
./vendor/bin/sail pint --dirty
```

- [ ] **Step 3: Manual smoke (optional)**

1. Settings: set `landlord_name`.
2. Create contract with ends_at → done step → PDF / copy link / WhatsApp.
3. Documents panel shows Contrato.
4. Edit rent/dates → Document replaced; no done step.
5. Create without landlord → blocked.

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Create generates Document category contract | 1 |
| `ends_at` required create/edit | 1 (+3 assert) |
| `landlord_name` required | 1 |
| send_email checkbox + ContractAgreementMail | 2 |
| Done step PDF / share / WhatsApp | 2 |
| No auto-redirect on create | 2 |
| Edit replaces Document | 3 |
| Edit no checkbox/done | 3 |
| Renewal / documents.shared untouched | all |
| Tests listed in spec | 1–3 |
