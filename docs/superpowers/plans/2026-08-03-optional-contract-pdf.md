# Optional Contract PDF Generation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make lease-agreement PDF generation optional on contract create, edit, and renew via a checked-by-default checkbox; when off, skip PDF/mail/share actions and show only «Ver detalle» on the done step.

**Architecture:** Add `bool $generate_pdf = true` on `CreateModal` and `RenewWizard`. Gate `GenerateLeaseAgreementPdfAction`, `ContractAgreementMail`, and share URLs behind that flag. Pass `generate_pdf` into `RenewContractAction` and skip landlord assertion + PDF when false. Blades already hide share buttons when URLs are null.

**Tech Stack:** Laravel 11, Livewire 4, PHPUnit via Sail, Pint.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-08-03-optional-contract-pdf-design.md`.
- Always run via `./vendor/bin/sail` (tests, pint).
- Diff mínimo: Livewire create/renew + RenewContractAction + i18n + tests. No Settings, no migrations, no RBAC changes.
- Default: `generate_pdf = true` (current behavior preserved).
- Email only when `generate_pdf` is true; force `send_email = false` when PDF is unchecked.
- Landlord name required only when generating PDF.
- Edit with PDF off: leave existing contract Documents untouched.
- No commit unless the user explicitly asks (during execution, include commit steps only if the user opted into commits).

---

## File Map

| File | Responsibility |
|------|----------------|
| `lang/es/contracts.php`, `lang/en/contracts.php` | Checkbox label string |
| `app/Livewire/Contracts/CreateModal.php` | `generate_pdf` flag, gates for landlord/PDF/mail/URLs |
| `resources/views/livewire/contracts/create-modal.blade.php` | Checkbox; email requires `generate_pdf` |
| `app/Actions/Contracts/RenewContractAction.php` | Accept `generate_pdf`; skip landlord/PDF/mail when false |
| `app/Livewire/Contracts/RenewWizard.php` | `generate_pdf` flag; pass to action; URLs only if PDF |
| `resources/views/livewire/contracts/renew-wizard.blade.php` | Checkbox; email requires `generate_pdf`; landlord banner only if PDF on |
| `tests/Feature/Contracts/ContractCreateModalTest.php` | Create/edit without PDF; mail forced off |
| `tests/Feature/Contracts/RenewWizardTest.php` | Renew without PDF; done-step URLs null |
| `tests/Feature/Contracts/ContractAgreementSendTest.php` | Action-level `generate_pdf` false |

`RenewContractResult::$document` is already `?Document` — no change required.

---

### Task 1: i18n + CreateModal create without PDF

**Files:**
- Modify: `lang/es/contracts.php`
- Modify: `lang/en/contracts.php`
- Modify: `app/Livewire/Contracts/CreateModal.php`
- Modify: `resources/views/livewire/contracts/create-modal.blade.php`
- Test: `tests/Feature/Contracts/ContractCreateModalTest.php`

**Interfaces:**
- Consumes: existing `GenerateLeaseAgreementPdfAction`, `ContractAgreementMail`, `ContractAgreementShareUrl`
- Produces: public `bool $generate_pdf = true` on `CreateModal`; create path skips PDF/mail/URLs when false; `updatedGeneratePdf(bool $value): void` clears `send_email` when false

- [ ] **Step 1: Write the failing tests**

Add to `ContractCreateModalTest.php`:

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/sail test --filter='test_open_create_defaults_generate_pdf_to_true|test_create_without_generate_pdf_skips_document_and_share_actions|test_unchecking_generate_pdf_clears_send_email'
```

Expected: FAIL (property `generate_pdf` missing / still generates PDF).

- [ ] **Step 3: Add i18n keys**

In `lang/es/contracts.php` (near `send_agreement_email`):

```php
'generate_contract_pdf' => 'Generar PDF del contrato',
```

In `lang/en/contracts.php`:

```php
'generate_contract_pdf' => 'Generate contract PDF',
```

- [ ] **Step 4: Implement CreateModal create gates**

In `CreateModal.php`:

1. Add property after `$send_email`:

```php
public bool $generate_pdf = true;
```

2. Add hook:

```php
public function updatedGeneratePdf(bool $value): void
{
    if (! $value) {
        $this->send_email = false;
    }
}
```

3. In `updatedTenantId`, only auto-enable email when PDF is on:

```php
public function updatedTenantId(?int $value): void
{
    if (! (auth()->user()?->can('receipts.send') ?? false) || ! $this->generate_pdf) {
        $this->send_email = false;

        return;
    }

    if ($value === null || $value <= 0) {
        $this->send_email = false;

        return;
    }

    $tenant = Tenant::query()->find($value);
    $email = is_string($tenant?->email) ? trim($tenant->email) : '';

    $this->send_email = $email !== '';
}
```

4. In `save()`, change landlord gate to:

```php
if ($this->generate_pdf && ! $this->assertLandlordNameConfigured($organizationSettingsService)) {
    return null;
}
```

5. Wrap the create PDF/mail/URL block:

```php
if ($isNew) {
    if ($this->generate_pdf) {
        try {
            $generateLeaseAgreementPdfAction->execute($contract->fresh(), auth()->id());
        } catch (ValidationException $e) {
            $this->addError('landlord_name', $e->errors()['landlord_name'][0] ?? __('contracts.validation.renew_failed'));

            return null;
        }

        $contract->loadMissing('tenant');
        $tenantEmail = is_string($contract->tenant?->email) ? trim($contract->tenant->email) : '';
        $sendEmail = $this->send_email
            && (auth()->user()?->can('receipts.send') ?? false)
            && $tenantEmail !== '';

        if ($sendEmail) {
            try {
                Mail::to($tenantEmail)->send(new ContractAgreementMail($contract));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $contract = $contract->fresh(['tenant', 'unit.property']);
        $this->pdfUrl = route('contracts.agreement.pdf', ['contractId' => $contract->id]);
        $this->shareUrl = ContractAgreementShareUrl::make($contract->id);
        $this->whatsAppUrl = $this->buildContractWhatsAppUrl($contract, $this->shareUrl, $organizationSettingsService);
    } else {
        $contract = $contract->fresh(['tenant', 'unit.property']);
        $this->pdfUrl = null;
        $this->shareUrl = null;
        $this->whatsAppUrl = null;
    }

    $this->createdContractId = $contract->id;
    $this->tenantName = (string) ($contract->tenant?->full_name ?? '');
    $this->unitLabel = trim((string) ($contract->unit?->property?->name.' / '.$contract->unit?->name));
    $this->step = 'done';
    session()->flash('success', __('contracts.flash.contract_created'));
    $this->dispatch('contract-updated');

    return null;
}
```

6. Include `'generate_pdf'` in `resetForm()` `$this->reset([...])`, then after reset set:

```php
$this->generate_pdf = true;
```

(Livewire `reset` restores the property default of `true`; explicit set is fine for clarity.)

- [ ] **Step 5: Update create-modal blade**

Replace the email-only block (before the action buttons) with:

```blade
<div class="md:col-span-2 space-y-2">
    <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" wire:model.live="generate_pdf" class="rounded border-slate-300" />
        {{ __('contracts.generate_contract_pdf') }}
    </label>

    @if (! $isEdit && $generate_pdf && $canSendReceipts && $selectedTenantEmail)
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" wire:model="send_email" class="rounded border-slate-300" />
            {{ __('contracts.send_agreement_email') }}
        </label>
    @endif
</div>
```

Done-step already gates Ver PDF / link / WhatsApp on `$pdfUrl` / `$shareUrl` / `$whatsAppUrl` and always shows Ver detalle via `$createdContractId` — no done-step HTML change required.

- [ ] **Step 6: Run tests to verify they pass**

```bash
./vendor/bin/sail test --filter='test_open_create_defaults_generate_pdf_to_true|test_create_without_generate_pdf_skips_document_and_share_actions|test_unchecking_generate_pdf_clears_send_email|test_create_generates_contract_category_document|test_create_send_email_dispatches_contract_agreement_mail'
```

Expected: PASS

---

### Task 2: CreateModal edit without PDF

**Files:**
- Modify: `app/Livewire/Contracts/CreateModal.php` (edit branch only)
- Test: `tests/Feature/Contracts/ContractCreateModalTest.php`

**Interfaces:**
- Consumes: `replaceContractAgreementDocument()` existing private method
- Produces: edit save skips regenerate when `generate_pdf` is false; existing Documents unchanged

- [ ] **Step 1: Write the failing test**

```php
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
```

(Adapt factory fields to match helpers already used in `test_edit_replaces_generated_contract_category_document`.)

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/sail test --filter=test_edit_without_generate_pdf_preserves_existing_document
```

Expected: FAIL (document regenerated/replaced, or landlord still required depending on path).

- [ ] **Step 3: Gate edit regenerate**

Replace the edit PDF block in `save()` with:

```php
if ($this->generate_pdf) {
    try {
        if (! $this->replaceContractAgreementDocument($contract, $generateLeaseAgreementPdfAction)) {
            $this->addError('contract_document', __('contracts.validation.manual_contract_document_blocks_regenerate'));

            return null;
        }
    } catch (ValidationException $e) {
        $this->addError('landlord_name', $e->errors()['landlord_name'][0] ?? __('contracts.validation.renew_failed'));

        return null;
    }
}

session()->flash('success', __('contracts.flash.contract_updated'));
$this->close();
$this->dispatch('contract-updated');

return null;
```

Ensure the create-modal checkbox for `generate_pdf` is visible on edit too (Task 1 blade already shows it outside the `! $isEdit` email condition).

- [ ] **Step 4: Run related edit tests**

```bash
./vendor/bin/sail test --filter='test_edit_without_generate_pdf_preserves_existing_document|test_edit_replaces_generated_contract_category_document|test_edit_preserves_manual_upload_contract_document|test_edit_modal_updates_contract_and_dispatches_event'
```

Expected: PASS  
Note: `test_edit_replaces_generated_contract_category_document` relies on default `generate_pdf = true`.

---

### Task 3: RenewContractAction accepts `generate_pdf`

**Files:**
- Modify: `app/Actions/Contracts/RenewContractAction.php`
- Test: `tests/Feature/Contracts/ContractAgreementSendTest.php`

**Interfaces:**
- Consumes: `input['generate_pdf']` bool (default `true` for backward compatibility)
- Produces: when false → no landlord assert, no PDF, no mail, `document: null` on result

- [ ] **Step 1: Write the failing tests**

In `ContractAgreementSendTest.php`:

```php
public function test_renew_without_generate_pdf_skips_document_and_email(): void
{
    Mail::fake();
    Storage::fake('local');
    config(['filesystems.documents_disk' => 'local']);

    $source = $this->createRenewableSource(email: 'tenant@example.com');

    $result = app(RenewContractAction::class)->execute(
        source: $source,
        input: [
            'starts_at' => '2026-08-01',
            'ends_at' => '2027-07-31',
            'rent_amount' => 10000,
            'deposit_amount' => 10000,
            'register_difference' => false,
            'send_email' => true,
            'generate_pdf' => false,
        ],
        userId: null,
    );

    $this->assertNull($result->document);
    $this->assertFalse(
        \App\Models\Document::query()->withoutOrganizationScope()
            ->where('documentable_type', Contract::class)
            ->where('documentable_id', $result->newContract->id)
            ->where('category', \App\Support\ContractDocumentCategory::Contract->value)
            ->exists()
    );
    Mail::assertNothingSent();
}

public function test_renew_without_generate_pdf_does_not_require_landlord(): void
{
    Storage::fake('local');
    config(['filesystems.documents_disk' => 'local']);

    $source = $this->createRenewableSource(email: 'tenant@example.com');
    // Clear landlord if helper seeded one:
    \App\Models\OrganizationSetting::query()
        ->withoutOrganizationScope()
        ->where('organization_id', $source->organization_id)
        ->update(['landlord_name' => null]);

    $result = app(RenewContractAction::class)->execute(
        source: $source,
        input: [
            'starts_at' => '2026-08-01',
            'ends_at' => '2027-07-31',
            'rent_amount' => 10000,
            'deposit_amount' => 10000,
            'register_difference' => false,
            'generate_pdf' => false,
        ],
        userId: null,
    );

    $this->assertNotNull($result->newContract);
    $this->assertNull($result->document);
}
```

(Adjust OrganizationSetting clear to match how `createRenewableSource` stores landlord — inspect helper; if settings row uses JSON/meta, clear that field the same way.)

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/sail test --filter='test_renew_without_generate_pdf_skips_document_and_email|test_renew_without_generate_pdf_does_not_require_landlord'
```

Expected: FAIL (PDF still generated / landlord ValidationException).

- [ ] **Step 3: Implement Action gate**

Update PHPDoc `$input` shape to include `generate_pdf?: bool`.

At start of `execute()`:

```php
$generatePdf = (bool) ($input['generate_pdf'] ?? true);

if ($generatePdf) {
    $this->assertLandlordNameConfigured((int) $source->organization_id);
}
```

After the transaction, replace the unconditional PDF/mail block with:

```php
$newContract = $transactionResult['newContract']->fresh();
$document = null;

if ($generatePdf) {
    $document = $this->generateLeaseAgreementPdfAction->execute(
        $newContract,
        $userId,
    );

    if ((bool) ($input['send_email'] ?? false)) {
        $this->sendContractAgreementEmail($newContract);
    }
}

return new RenewContractResult(
    newContract: $transactionResult['newContract'],
    oldContract: $transactionResult['oldContract'],
    transferOutCharge: $transactionResult['transferOutCharge'],
    transferredHoldCharge: $transactionResult['transferredHoldCharge'],
    differenceHoldCharge: $transactionResult['differenceHoldCharge'],
    transferredAmount: $transactionResult['transferredAmount'],
    differenceAmount: $transactionResult['differenceAmount'],
    document: $document,
);
```

- [ ] **Step 4: Run action tests**

```bash
./vendor/bin/sail test --filter=ContractAgreementSendTest
```

Expected: PASS (existing tests omit `generate_pdf` → default true).

---

### Task 4: RenewWizard checkbox + done-step

**Files:**
- Modify: `app/Livewire/Contracts/RenewWizard.php`
- Modify: `resources/views/livewire/contracts/renew-wizard.blade.php`
- Test: `tests/Feature/Contracts/RenewWizardTest.php`

**Interfaces:**
- Consumes: `RenewContractAction::execute(..., input['generate_pdf'])`
- Produces: `bool $generate_pdf = true`; when false, `pdfUrl`/`shareUrl`/`whatsAppUrl` stay null; `send_email` cleared via `updatedGeneratePdf`

- [ ] **Step 1: Write the failing test**

```php
public function test_renew_without_generate_pdf_shows_only_detail_action(): void
{
    Mail::fake();
    Storage::fake('local');
    config(['filesystems.documents_disk' => 'local']);

    CarbonImmutable::setTestNow('2026-08-01 10:00:00');

    [$organization, $contract, $user] = $this->createRenewableGraph(
        email: 'tenant@example.com',
        phone: '526611234567',
    );

    Livewire::actingAs($user)
        ->test(RenewWizard::class)
        ->dispatch('open-contract-renew', contractId: $contract->id)
        ->assertSet('generate_pdf', true)
        ->set('generate_pdf', false)
        ->assertSet('send_email', false)
        ->set('rent_amount', '10000')
        ->set('deposit_amount', '10000')
        ->set('starts_at', '2026-08-01')
        ->set('ends_at', '2027-07-31')
        ->set('send_email', true) // must be ignored server-side when PDF off
        ->call('renew')
        ->assertHasNoErrors()
        ->assertSet('step', 'done')
        ->assertNotSet('newContractId', null)
        ->assertSet('pdfUrl', null)
        ->assertSet('shareUrl', null)
        ->assertSet('whatsAppUrl', null)
        ->assertDispatched('contract-renewed');

    Mail::assertNothingSent();

    CarbonImmutable::setTestNow();
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/sail test --filter=test_renew_without_generate_pdf_shows_only_detail_action
```

Expected: FAIL.

- [ ] **Step 3: Implement RenewWizard**

1. Add `public bool $generate_pdf = true;` next to `$send_email`.

2. Add:

```php
public function updatedGeneratePdf(bool $value): void
{
    if (! $value) {
        $this->send_email = false;
    }
}
```

3. In `renew()`, compute:

```php
$generatePdf = $this->generate_pdf;
$sendEmail = $generatePdf
    && $this->send_email
    && (auth()->user()?->can('receipts.send') ?? false);
```

Pass into action input:

```php
'generate_pdf' => $generatePdf,
'send_email' => $sendEmail,
```

4. After success, only build URLs when `$generatePdf`:

```php
$newContract = $result->newContract->fresh(['tenant', 'unit.property']);
$this->newContractId = $newContract->id;

if ($generatePdf) {
    $this->pdfUrl = route('contracts.agreement.pdf', ['contractId' => $newContract->id]);
    $this->shareUrl = ContractAgreementShareUrl::make($newContract->id);
    $this->whatsAppUrl = $this->buildContractWhatsAppUrl($newContract, $this->shareUrl, $settingsService);
} else {
    $this->pdfUrl = null;
    $this->shareUrl = null;
    $this->whatsAppUrl = null;
}
```

5. In `rules()`, add `'generate_pdf' => ['boolean']`.

6. Include `generate_pdf` in `resetForm()` reset list; default remains `true`.

- [ ] **Step 4: Update renew-wizard blade**

1. Show landlord warning only when PDF will be generated:

```blade
@unless ($landlordConfigured)
    @if ($generate_pdf)
        <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            {{ __('contracts.renew_landlord_required') }}
            <a href="{{ route('settings.index') }}" class="font-medium underline hover:text-amber-900">
                {{ __('contracts.renew_go_to_settings') }}
            </a>
        </div>
    @endif
@endunless
```

2. Replace email checkbox section with:

```blade
<div class="md:col-span-2 space-y-2">
    <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
        <input type="checkbox" wire:model.live="generate_pdf" class="rounded accent-slate-700">
        <span>{{ __('contracts.generate_contract_pdf') }}</span>
    </label>

    @if ($generate_pdf && $canSendEmail && $tenantEmail)
        <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
            <input type="checkbox" wire:model.live="send_email" class="rounded accent-slate-700">
            <span>{!! __('contracts.send_contract_email', ['email' => '<strong>'.$tenantEmail.'</strong>']) !!}</span>
        </label>
    @endif
</div>
```

Done-step already shows Ver detalle via `$newContractId` and hides share actions when URLs are null.

- [ ] **Step 5: Run renew wizard tests**

```bash
./vendor/bin/sail test --filter=RenewWizardTest
```

Expected: PASS (happy path still defaults `generate_pdf` true and shows PDF/share).

---

### Task 5: Full verification + format

**Files:** none new

- [ ] **Step 1: Run the related suite**

```bash
./vendor/bin/sail test --filter='ContractCreateModalTest|RenewWizardTest|ContractAgreementSendTest|LeaseAgreementPdfTest'
```

Expected: PASS

- [ ] **Step 2: Pint dirty files**

```bash
./vendor/bin/sail pint --dirty
```

Expected: clean / fixed formatting

- [ ] **Step 3: Manual smoke (optional)**

1. Crear contrato con checkbox PDF marcado → done con Ver PDF / link / WhatsApp / Ver detalle.
2. Crear con PDF desmarcado → done solo Ver detalle; sin Document en panel.
3. Editar con PDF desmarcado → datos guardados; Document previo intacto.
4. Renovar con PDF desmarcado → done solo Ver detalle; sin correo.

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Checkbox default on (create/edit/renew) | 1, 2, 4 |
| Email hidden/disabled when PDF off | 1, 4 |
| Force `send_email = false` when unchecking PDF | 1, 4 |
| Create without PDF: no Document, done without share | 1 |
| Edit without PDF: preserve existing Document | 2 |
| Renew without PDF: Action + Wizard | 3, 4 |
| Landlord only when generating PDF | 1, 3, 4 |
| No RBAC/migration/Settings | all (out of scope) |
