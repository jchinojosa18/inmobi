# Deposit Refund Receipt Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** On settlement with `depositRefund > 0`, generate a `DEV-YYYY-#####` PDF receipt, store it as a Contract `Document`, and link it from the Finiquito panel.

**Architecture:** Folio + PDF Actions mirror deposit-hold / lease-agreement patterns. `ProcessContractSettlementAction` calls the PDF Action after creating the refund Expense (same DB transaction). Wizard reads `meta.settlements.*.refund_receipt_document_id` for the viewer link. Documents panel overrides label when `meta.kind === deposit_refund_receipt` (no new enum case — avoids upload-category clutter).

**Tech Stack:** Laravel 11, Livewire 4, DomPDF (`Barryvdh\DomPDF`), PHPUnit via Sail, Pint.

## Global Constraints

- Run all commands via `./vendor/bin/sail`.
- Spec: `docs/superpowers/specs/2026-08-06-deposit-refund-receipt-design.md`.
- Folio prefix exact: `DEV-{Y}-` + 5-digit pad; forward-only (no backfill).
- Do not change on-the-fly settlement PDF controller behavior.
- Keep `canSettleContracts` visible for ended (`!cancelled` + `contracts.settle`) — uncommitted fix in `Show.php` must ship with this work.
- No commit unless the user explicitly asks (repo user rule). Skip plan Commit steps until asked.
- Tests: `./vendor/bin/sail test --filter=<relevant>`; `./vendor/bin/sail pint --dirty`.

---

## File Map

| File | Responsibility |
|------|----------------|
| `app/Actions/Contracts/GenerateDepositRefundReceiptFolioAction.php` | `DEV-YYYY-#####` sequence from Document meta |
| `app/Actions/Contracts/GenerateDepositRefundReceiptPdfAction.php` | DomPDF + `Document::storeNew` on Contract |
| `resources/views/pdf/deposit-refund-receipt.blade.php` | PDF markup |
| `app/Actions/Contracts/ProcessContractSettlementAction.php` | Call PDF action; write meta keys |
| `app/Livewire/Contracts/SettlementWizard.php` + blade | Link “Ver recibo de devolución” |
| `app/Livewire/Documents/Panel.php` | Label override for refund receipts |
| `app/Livewire/Contracts/Show.php` | Ensure ended shows Finiquito panel (if not already merged) |
| `lang/{es,en}/contracts.php` | Receipt link + document label keys |
| `docs/AI_ONBOARDING.md` | §4.3 one line |
| Tests under `tests/Unit/Actions/` and `tests/Feature/Contracts/` | Folio, settlement Document, wizard link |

---

### Task 1: Folio + PDF Action + blade

**Files:**
- Create: `app/Actions/Contracts/GenerateDepositRefundReceiptFolioAction.php`
- Create: `app/Actions/Contracts/GenerateDepositRefundReceiptPdfAction.php`
- Create: `resources/views/pdf/deposit-refund-receipt.blade.php`
- Create: `tests/Unit/Actions/GenerateDepositRefundReceiptFolioActionTest.php`
- Create: `tests/Unit/Actions/GenerateDepositRefundReceiptPdfActionTest.php`

**Interfaces:**
- Consumes: `Document`, `Contract`, DomPDF, Storage, `ContractDocumentCategory::Contract`
- Produces:
  - `GenerateDepositRefundReceiptFolioAction::execute(int $organizationId, CarbonInterface $at): string`
  - `GenerateDepositRefundReceiptPdfAction::execute(Contract $contract, array $summary, int $refundExpenseId, ?int $userId): Document`  
    where `$summary` includes at least: `folio`, `move_out_date`, `deposit_available`, `deposit_applied`, `deposit_refund`, `credit_refunded`, `settlement_batch_id`

- [ ] **Step 1: Failing folio test**

```php
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
```

- [ ] **Step 2: Run — expect FAIL**

```bash
./vendor/bin/sail test --filter=GenerateDepositRefundReceiptFolioActionTest
```

- [ ] **Step 3: Implement folio Action**

Mirror `GenerateDepositReceiptFolioAction`, but query `Document` (withTrashed) where `meta.kind = deposit_refund_receipt` / `meta.folio` LIKE `DEV-{year}-%`. Use `json_extract` / `JSON_EXTRACT` like the deposit folio action.

- [ ] **Step 4: Failing PDF Action test**

```php
public function test_execute_stores_document_on_contract_with_folio_meta(): void
{
    $organization = Organization::factory()->create();
    // property/unit/tenant + contract with relations loaded
    $contract = /* factory graph */;
    $document = app(GenerateDepositRefundReceiptPdfAction::class)->execute(
        contract: $contract,
        summary: [
            'folio' => 'DEV-2026-00001',
            'move_out_date' => '2026-08-06',
            'deposit_available' => 7500.0,
            'deposit_applied' => 5000.0,
            'deposit_refund' => 2500.0,
            'credit_refunded' => 0.0,
            'settlement_batch_id' => 'batch-1',
        ],
        refundExpenseId: 99,
        userId: null,
    );

    $this->assertSame(Contract::class, $document->documentable_type);
    $this->assertSame($contract->id, $document->documentable_id);
    $this->assertSame('deposit_refund_receipt', data_get($document->meta, 'kind'));
    $this->assertSame('DEV-2026-00001', data_get($document->meta, 'folio'));
    $this->assertContains('deposit_refund', $document->tags);
    Storage::disk(config('filesystems.documents_disk', 'local'))->assertExists($document->path);
}
```

- [ ] **Step 5: Implement PDF Action + blade**

Blade: style like `pdf/contract-settlement.blade.php` (DejaVu, boxes). Include fields from spec §PDF content.

`Document::storeNew` fields per spec. Category = `ContractDocumentCategory::Contract`. Path folder `documents/contract/{orgId}/`.

Generate folio inside PDF Action **or** accept folio in `$summary` (prefer caller passes folio already generated under lock in settlement txn).

- [ ] **Step 6: Green + pint**

```bash
./vendor/bin/sail test --filter=GenerateDepositRefundReceipt
./vendor/bin/sail pint --dirty
```

- [ ] **Step 7: Commit (only if user asked)**

---

### Task 2: Wire into ProcessContractSettlementAction

**Files:**
- Modify: `app/Actions/Contracts/ProcessContractSettlementAction.php`
- Modify: `tests/Unit/Actions/ProcessContractSettlementActionTest.php`
- Modify: `docs/AI_ONBOARDING.md` (§4.3)

**Interfaces:**
- Consumes: Task 1 Actions
- Produces: `meta.settlements[batch].refund_receipt_folio`, `refund_receipt_document_id`

- [ ] **Step 1: Extend existing settlement test**

In `test_deposit_covers_all_and_generates_refund_expense` (refund 500), add:

```php
$contract->refresh();
$batchId = data_get($contract->meta, 'settlement_batch_id');
$this->assertNotEmpty(data_get($contract->meta, "settlements.{$batchId}.refund_receipt_folio"));
$this->assertNotEmpty(data_get($contract->meta, "settlements.{$batchId}.refund_receipt_document_id"));
$this->assertTrue(str_starts_with(
    (string) data_get($contract->meta, "settlements.{$batchId}.refund_receipt_folio"),
    'DEV-'
));
$this->assertDatabaseHas('documents', [
    'documentable_type' => Contract::class,
    'documentable_id' => $contract->id,
]);
```

Add assertion on a partial-refund / zero-refund test that **no** refund receipt document exists when `depositRefund === 0` (use `test_deposit_partial_leaves_balance_to_collect` or equivalent).

- [ ] **Step 2: Run — expect FAIL**

```bash
./vendor/bin/sail test --filter=test_deposit_covers_all_and_generates_refund_expense
```

- [ ] **Step 3: Wire Action**

Inject `GenerateDepositRefundReceiptFolioAction` + `GenerateDepositRefundReceiptPdfAction` (or resolve via `app()` consistent with nearby code).

Inside `if ($depositRefund > 0)` after `$refundExpenseId = ...`:

```php
$folio = $this->refundReceiptFolioAction->execute(
    $lockedContract->organization_id,
    $exitDate,
);
$receiptDocument = $this->refundReceiptPdfAction->execute(
    contract: $lockedContract->loadMissing(['tenant', 'unit.property']),
    summary: [
        'folio' => $folio,
        'move_out_date' => $exitDate->toDateString(),
        'deposit_available' => $depositAvailable,
        'deposit_applied' => $depositApplied,
        'deposit_refund' => $depositRefund,
        'credit_refunded' => $creditRefunded,
        'settlement_batch_id' => $batchId,
    ],
    refundExpenseId: (int) $refundExpenseId,
    userId: $userId,
);
$refundReceiptFolio = $folio;
$refundReceiptDocumentId = $receiptDocument->id;
```

Add to settlements array:

```php
'refund_receipt_folio' => $refundReceiptFolio ?? null,
'refund_receipt_document_id' => $refundReceiptDocumentId ?? null,
```

Initialize both nulls before the `if ($depositRefund > 0)` block.

- [ ] **Step 4: AI_ONBOARDING §4.3**

One bullet: al devolver depósito se genera recibo PDF `DEV-…` como Document del contrato.

- [ ] **Step 5: Green + pint**

```bash
./vendor/bin/sail test --filter=ProcessContractSettlementActionTest
./vendor/bin/sail pint --dirty
```

- [ ] **Step 6: Commit (only if user asked)**

---

### Task 3: Wizard link + Documents label + ended panel

**Files:**
- Modify: `app/Livewire/Contracts/SettlementWizard.php`
- Modify: `resources/views/livewire/contracts/settlement-wizard.blade.php`
- Modify: `app/Livewire/Documents/Panel.php`
- Modify: `app/Livewire/Contracts/Show.php` (ended visibility — include if still dirty)
- Modify: `lang/es/contracts.php`, `lang/en/contracts.php`
- Modify: `tests/Feature/Contracts/SettlementWizardSurplusTest.php`

**Interfaces:**
- Consumes: `refund_receipt_document_id` from settlements meta
- Produces: view var `refundReceiptUrl` (`?string`); i18n `contracts.view_deposit_refund_receipt`; documents label key `contracts.deposit_refund_receipt_document`

- [ ] **Step 1: Failing Feature tests**

Extend `SettlementWizardSurplusTest`:

1. When ended + expense + meta with `refund_receipt_document_id` pointing at a real Document → `assertSee(__('contracts.view_deposit_refund_receipt'))` and `assertSeeHtml` download URL.
2. Keep existing “show page includes settlement panel” for ended (`canSettleContracts` true).

Ensure `Show.php` uses:

```php
'canSettleContracts' => ! $contract->isCancelled()
    && (auth()->user()?->can('contracts.settle') ?? false),
```

- [ ] **Step 2: Run — expect FAIL**

```bash
./vendor/bin/sail test --filter=SettlementWizardSurplusTest
```

- [ ] **Step 3: i18n**

ES:
```php
'view_deposit_refund_receipt' => 'Ver recibo de devolución',
'deposit_refund_receipt_document' => 'Recibo devolución depósito',
```

EN:
```php
'view_deposit_refund_receipt' => 'View refund receipt',
'deposit_refund_receipt_document' => 'Deposit refund receipt',
```

- [ ] **Step 4: SettlementWizard render**

Resolve document id from settlements (prefer batch in `settlement_batch_id`):

```php
$refundReceiptUrl = null;
$batchId = data_get($contract->meta, 'settlement_batch_id');
$documentId = (int) data_get($contract->meta, "settlements.{$batchId}.refund_receipt_document_id", 0);
if ($documentId > 0 && $refundedDeposit > 0) {
    $refundReceiptUrl = route('documents.download', ['document' => $documentId]);
}
```

Pass to view. Blade (ended surplus block): link next to gastos link.

Prefer `FileViewerItem` + `x-ui.file-viewer-trigger` if easy (match settlement PDF pattern); else plain `href` download is acceptable for v1.

- [ ] **Step 5: Documents Panel label**

```php
'category_label' => data_get($document->meta, 'kind') === 'deposit_refund_receipt'
    ? __('contracts.deposit_refund_receipt_document')
        .(data_get($document->meta, 'folio') ? ' · '.data_get($document->meta, 'folio') : '')
    : $document->category?->label(),
```

- [ ] **Step 6: Green all related + pint**

```bash
./vendor/bin/sail test --filter=SettlementWizardSurplusTest
./vendor/bin/sail test --filter=ProcessContractSettlementActionTest
./vendor/bin/sail test --filter=GenerateDepositRefundReceipt
./vendor/bin/sail test --filter=CancelContractShowTest
./vendor/bin/sail pint --dirty
```

- [ ] **Step 7: Commit (only if user asked)**

---

## Spec coverage checklist

| Spec AC | Task |
|---------|------|
| Document + DEV folio on refund | 1–2 |
| meta refund_receipt_* | 2 |
| No document when refund 0 | 2 |
| Wizard link when document id | 3 |
| Shows in Documentos panel | 3 (label) |
| No backfill | Global |
| Ended Finiquito visible | 3 / Show.php |

## Self-review notes

- Folio stored on **Document.meta**, not Charge — sequence query must use Documents.
- PDF generation inside settlement transaction: DomPDF + Storage write must succeed or roll back expense; if Storage is non-transactional, still fail the Action on error so settlement doesn’t complete without receipt.
- Do not add `deposit_refund` to upload category dropdown.
