# Contract renewal + PDF + send — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Renovar un contrato activo/vencido creando uno nuevo, transfiriendo el depósito con `DEPOSIT_TRANSFER_OUT` + nuevo `DEPOSIT_HOLD`, generando PDF DomPDF, y permitiendo email/WhatsApp.

**Architecture:** `RenewContractAction` orquesta cierre del origen, ledger de depósito, alta del nuevo contrato (hooks de RENT) y PDF. UI Livewire wizard + badge Vencido. Settings de arrendador/plantillas. Share URL firmada espejo de recibos.

**Tech Stack:** Laravel 11, Livewire 4, DomPDF (`barryvdh/laravel-dompdf`), CarbonImmutable, PHPUnit via Sail, Spatie permissions.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-08-01-contract-renewal-design.md`
- Timezone: `America/Tijuana`
- `DEPOSIT_TRANSFER_OUT`: monto **negativo** (como `DEPOSIT_APPLY`); UI muestra absoluto
- Excluir `DEPOSIT_TRANSFER_OUT` de ingresos operativos y de `outstandingBalanceExcludingDepositHold` (si no, un transfer -9500 oculta adeudos)
- `availableDepositAmount` resta `|DEPOSIT_TRANSFER_OUT|`
- Solo renovar origen `status=active` sin `meta.settlement_batch_id`
- Bloquear si `outstandingBalanceExcludingDepositHold > 0`
- PDF solo (no DOCX runtime); DOCX de referencia opcional en `docs/legal/`
- Envío: permiso `receipts.send` (v1)
- Sail: `./vendor/bin/sail test --filter=...` y `./vendor/bin/sail pint --dirty`
- No push; commits solo si el usuario lo pide (salvo que el worker tenga instrucción explícita de commit por task)

## File map

| File | Role |
|------|------|
| `app/Models/Charge.php` | Const `TYPE_DEPOSIT_TRANSFER_OUT` |
| `app/Support/DepositBalanceService.php` | `transferredOutDepositAmount`, ajustar available + outstanding |
| `app/Actions/Contracts/RenewContractAction.php` | Caso de uso |
| `app/Actions/Contracts/GenerateLeaseAgreementPdfAction.php` | DomPDF + Document |
| `app/Support/MoneyToWords.php` | Renta en letra (es_MX) |
| `app/Support/ContractAgreementShareUrl.php` | Signed URL |
| `app/Mail/ContractAgreementMail.php` | Email + PDF |
| `app/Http/Controllers/ContractAgreementPdfController.php` | Download + share |
| `app/Livewire/Contracts/RenewWizard.php` | Wizard UI |
| `resources/views/livewire/contracts/renew-wizard.blade.php` | Vista wizard |
| `resources/views/pdf/lease-agreement.blade.php` | Plantilla legal |
| `resources/views/emails/contract-agreement.blade.php` | Email body |
| `database/migrations/*_add_contract_landlord_settings.php` | Columnas settings |
| `app/Models/OrganizationSetting.php` + `OrganizationSettingsService` | landlord + templates |
| `app/Livewire/Settings/Index.php` + blade | Campos UI |
| `app/Livewire/Contracts/Index.php` + `Show.php` + blades | Badge Vencido, CTA Renovar |
| `routes/web.php` | PDF + share routes |
| `lang/es/contracts.php` (+ settings) | Copy |
| `docs/AI_ONBOARDING.md` | Documentar flujo |
| `docs/legal/contrato-arrendamiento-referencia.docx` | Copia del DOCX fuente |
| Tests bajo `tests/Unit` y `tests/Feature/Contracts` | TDD |

---

### Task 1: `DEPOSIT_TRANSFER_OUT` + DepositBalanceService

**Files:**
- Modify: `app/Models/Charge.php`
- Modify: `app/Support/DepositBalanceService.php`
- Modify: `app/Livewire/Contracts/Show.php` (helpers `isDepositType` / labels si aplica)
- Modify: `app/Actions/MonthCloses/BuildMonthCloseSnapshotAction.php` (excluir tipo en cartera si lista explícita)
- Modify: `app/Support/ContractOverdueQuery.php` si lista exclusiones
- Test: `tests/Unit/Support/DepositBalanceServiceTest.php` (crear si no existe)

**Interfaces:**
- Produces: `Charge::TYPE_DEPOSIT_TRANSFER_OUT = 'DEPOSIT_TRANSFER_OUT'`
- Produces: `DepositBalanceService::transferredOutDepositAmount(Contract): float`
- Produces: `availableDepositAmount` = holds − apply − refund − transferOut
- Produces: `outstandingBalanceExcludingDepositHold` ignora `DEPOSIT_HOLD` **y** `DEPOSIT_TRANSFER_OUT`

- [ ] **Step 1: Write failing tests**

```php
public function test_transfer_out_zeros_available_deposit(): void
{
    // contract with DEPOSIT_HOLD 9500, then DEPOSIT_TRANSFER_OUT -9500
    // assert availableDepositAmount === 0.0
}

public function test_outstanding_ignores_transfer_out_and_keeps_rent_debt(): void
{
    // RENT 1000 unpaid + TRANSFER_OUT -9500
    // assert outstandingBalanceExcludingDepositHold === 1000.0
}
```

- [ ] **Step 2: Run tests (expect FAIL)**

```bash
./vendor/bin/sail test --filter=DepositBalanceServiceTest
```

- [ ] **Step 3: Implement type + service methods**

```php
// Charge.php
public const TYPE_DEPOSIT_TRANSFER_OUT = 'DEPOSIT_TRANSFER_OUT';

// DepositBalanceService
public function transferredOutDepositAmount(Contract $contract): float
{
    return round(abs((float) Charge::query()
        ->withoutOrganizationScope()
        ->where('organization_id', $contract->organization_id)
        ->where('contract_id', $contract->id)
        ->where('type', Charge::TYPE_DEPOSIT_TRANSFER_OUT)
        ->sum('amount')), 2);
}

public function availableDepositAmount(Contract $contract): float
{
    return round(max(
        $this->registeredDepositHoldAmount($contract)
        - $this->appliedDepositAmount($contract)
        - $this->refundedDepositAmount($contract)
        - $this->transferredOutDepositAmount($contract),
        0
    ), 2);
}

// In outstandingBalanceExcludingDepositHold queries, exclude TRANSFER_OUT:
->whereNotIn('type', [Charge::TYPE_DEPOSIT_HOLD, Charge::TYPE_DEPOSIT_TRANSFER_OUT])
// and same for charges.type in the allocations join filter
```

Update Show ledger labels / deposit-type checks to treat `DEPOSIT_TRANSFER_OUT` as depósito (no cobranza).

- [ ] **Step 4: Run tests + pint**

```bash
./vendor/bin/sail test --filter=DepositBalanceServiceTest
./vendor/bin/sail pint --dirty
```

- [ ] **Step 5: Commit** (solo si el usuario lo pidió)

---

### Task 2: `RenewContractAction` (core + depósito + diferencia)

**Files:**
- Create: `app/Actions/Contracts/RenewContractAction.php`
- Create: `app/Actions/Contracts/RenewContractResult.php` (readonly DTO)
- Test: `tests/Unit/Actions/RenewContractActionTest.php`

**Interfaces:**
- Consumes: `DepositBalanceService`, `RegisterDepositHoldAction` (para diff “ya recibido”), `Contract` factory/graph
- Produces:

```php
final readonly class RenewContractResult
{
    public function __construct(
        public Contract $newContract,
        public Contract $oldContract,
        public ?Charge $transferOutCharge,
        public ?Charge $transferredHoldCharge,
        public ?Charge $differenceHoldCharge,
        public float $transferredAmount,
        public float $differenceAmount,
    ) {}
}

// RenewContractAction::execute(
//   Contract $source,
//   array $input, // starts_at, ends_at, rent_amount, deposit_amount, due_day?, grace_days?, penalty_rate_daily?, register_difference: bool, difference_received_at?, difference_method?, notes?
//   ?int $userId,
// ): RenewContractResult
```

- [ ] **Step 1: Failing tests**

```php
public function test_renew_creates_new_contract_ends_old_and_transfers_deposit(): void
{
    // source active, ends_at past, DEPOSIT_HOLD 9500, no debt
    // renew rent 10000, deposit_amount 10000, register_difference true
    // assert new contract id != old, old status ended
    // assert TRANSFER_OUT -9500 on old, HOLD 9500 on new (meta transferred_from)
    // assert second HOLD 500 on new when register_difference
    // assert meta renewed_from / renewed_to
}

public function test_renew_blocked_when_outstanding_balance(): void
{
    // expect ValidationException
}

public function test_renew_blocked_when_settlement_batch_present(): void
{
    // meta.settlement_batch_id set → ValidationException
}
```

- [ ] **Step 2: Run (FAIL)**

```bash
./vendor/bin/sail test --filter=RenewContractActionTest
```

- [ ] **Step 3: Implement action (transaction)**

Pseudocode obligatorio:

1. Lock source `lockForUpdate`.
2. Guards: `status===active`, no `settlement_batch_id`, outstanding==0, no other active on unit, `landlord` check deferred to PDF task (opcional aquí: no bloquear por landlord aún).
3. `$available = depositBalance->availableDepositAmount($source)`.
4. Create new `Contract` active with input fields + `meta.renewed_from_contract_id`.
5. Update source: `status=ended`, set `ends_at` if needed, `meta.renewed_to_contract_id`.
6. If `$available > 0`: create `DEPOSIT_TRANSFER_OUT` amount `-$available` on source with meta; create `DEPOSIT_HOLD` `$available` on new via direct create **or** extend RegisterDepositHoldAction with `meta` source `deposit_transfer` (prefer create in action with folio opcional / sin folio de recibo si la transferencia no es cobro nuevo — documentar: hold transferido **sin** folio DEP nuevo; diff sí usa `RegisterDepositHoldAction`).
7. `$diff = max(deposit_amount - available, 0)`. If `register_difference && diff > 0`: `RegisterDepositHoldAction::execute(...)`.
8. Return DTO. RENT: relies on `Contract::created` hook.

**Hold transferido:** crear `Charge` `DEPOSIT_HOLD` con `meta`:

```php
[
  'source' => 'deposit_transfer',
  'transferred_from_contract_id' => $source->id,
  'transfer_out_charge_id' => $transferOut->id,
]
```

Sin PDF de recibo DEP para el hold heredado (no es cobro nuevo).

- [ ] **Step 4: Tests green + pint**

```bash
./vendor/bin/sail test --filter=RenewContractActionTest
./vendor/bin/sail pint --dirty
```

- [ ] **Step 5: Commit** (si aplica)

---

### Task 3: Settings — arrendador + plantillas contrato

**Files:**
- Create: `database/migrations/2026_08_01_120000_add_contract_agreement_settings_columns.php`
- Modify: `app/Models/OrganizationSetting.php`
- Modify: `app/Support/OrganizationSettingsService.php` (`defaults`, `normalize`, `templateVariables` si aplica)
- Modify: `app/Livewire/Settings/Index.php` + `resources/views/livewire/settings/index.blade.php`
- Modify: `lang/es/settings.php`
- Test: `tests/Feature/Settings/OrganizationSettingsTest.php` (extender)

**Interfaces:**
- Columns: `landlord_name` (string nullable), `landlord_rep` (string nullable), `contract_email_template` (text), `contract_whatsapp_template` (text)
- Defaults email/WhatsApp contrato con vars `{tenant_name}`, `{unit_name}`, `{shared_contract_url}`, `{rent_amount}`, `{starts_at}`, `{ends_at}`

- [ ] **Step 1: Migration + failing settings test** (guardar/cargar landlord_name)
- [ ] **Step 2: Implement model/service/UI**
- [ ] **Step 3: `./vendor/bin/sail test --filter=OrganizationSettingsTest` + pint**
- [ ] **Step 4: Commit** (si aplica)

---

### Task 4: PDF lease agreement + Document + download route

**Files:**
- Create: `app/Support/MoneyToWords.php`
- Create: `app/Actions/Contracts/GenerateLeaseAgreementPdfAction.php`
- Create: `resources/views/pdf/lease-agreement.blade.php` (portar cláusulas del DOCX)
- Create: `app/Http/Controllers/ContractAgreementPdfController.php`
- Modify: `routes/web.php`
- Copy: `docs/legal/contrato-arrendamiento-referencia.docx` desde Downloads
- Test: `tests/Feature/Contracts/LeaseAgreementPdfTest.php`

**Interfaces:**

```php
// GenerateLeaseAgreementPdfAction::execute(Contract $contract, ?int $userId): Document
// Requires organization_settings.landlord_name non-empty → ValidationException otherwise

// MoneyToWords::mxn(float $amount): string  // e.g. "NUEVE MIL QUINIENTOS PESOS 00/100 M.N."
```

Controller:
- Auth: `contracts.agreement.pdf` → stream PDF for contract (permission `contracts.view`)
- Signed: `contracts.agreement.share` → public temporary signed (like payment receipt share)

Persist Document morph to Contract: `type`/`tags` alineados a variant contract (ver `Documents\Panel`); `meta.generated=true`, `meta.kind=lease_agreement`.

- [ ] **Step 1: Failing feature test** — renew or factory contract + settings landlord → action creates Document; GET pdf 200
- [ ] **Step 2: Implement MoneyToWords, Blade (contenido legal completo del DOCX), action, routes**
- [ ] **Step 3: Wire PDF generation at end of `RenewContractAction`** (call generate; add `?Document $document` to result DTO)
- [ ] **Step 4: Tests + pint**
- [ ] **Step 5: Commit** (si aplica)

---

### Task 5: Email + WhatsApp share

**Files:**
- Create: `app/Support/ContractAgreementShareUrl.php`
- Create: `app/Mail/ContractAgreementMail.php`
- Create: `resources/views/emails/contract-agreement.blade.php`
- Modify: `RenewContractAction` or wizard to send mail
- Test: `tests/Feature/Contracts/ContractAgreementSendTest.php`

**Interfaces:**

```php
ContractAgreementShareUrl::make(int $contractId, ?DateTimeInterface $expiresAt = null): string
// temporarySignedRoute('contracts.agreement.share', +7 days)
```

Mail: attach PDF via `Pdf::loadView('pdf.lease-agreement', ...)`, body from `contract_email_template`.

WhatsApp helper (en Livewire):

```php
private function buildContractWhatsAppUrl(Contract $contract, string $shareUrl): ?string
{
    $phone = preg_replace('/\D+/', '', (string) $contract->tenant?->phone);
    if ($phone === '') return null;
    $text = app(OrganizationSettingsService::class)->renderTemplate(...);
    return 'https://wa.me/'.$phone.'?text='.rawurlencode($text);
}
```

- [ ] **Step 1: Failing tests** — Mail::fake; renew with tenant email → `Mail::assertSent(ContractAgreementMail::class)`; without email → not sent
- [ ] **Step 2: Implement share URL, mail, optional send flag `send_email` in action/wizard**
- [ ] **Step 3: Tests + pint**
- [ ] **Step 4: Commit** (si aplica)

Permission: gate send with `receipts.send` on Livewire methods.

---

### Task 6: `RenewWizard` Livewire UI

**Files:**
- Create: `app/Livewire/Contracts/RenewWizard.php`
- Create: `resources/views/livewire/contracts/renew-wizard.blade.php`
- Modify: `app/Livewire/Contracts/Show.php` + blade (botón Renovar, embed wizard)
- Modify: `resources/views/layouts` o show para montar componente
- Modify: `lang/es/contracts.php`
- Test: `tests/Feature/Contracts/RenewWizardTest.php`

**UI fields:** `starts_at`, `ends_at`, `rent_amount`, `deposit_amount` (default = rent), `due_day`, `grace_days`, checkbox `register_difference`, `send_email` (default true si hay email), post-success: link PDF, botón WhatsApp, flash.

Open via `Livewire.dispatch('open-contract-renew', { contractId })` (mismo estilo que create modal).

- [ ] **Step 1: Feature test** Livewire renew happy path + blocked outstanding
- [ ] **Step 2: Implement wizard + wire Show CTA**
- [ ] **Step 3: Tests + pint**
- [ ] **Step 4: Commit** (si aplica)

---

### Task 7: Badge “Vencido” + index filter

**Files:**
- Modify: `app/Livewire/Contracts/Index.php` + `resources/views/livewire/contracts/index.blade.php`
- Modify: `app/Livewire/Contracts/Show.php` + blade (banner)
- Optional helper: `app/Support/ContractStatusPresenter.php` or method on model `isExpired(): bool`
- Test: `tests/Feature/Contracts/ContractExpiredBadgeTest.php`

```php
public function isExpired(?CarbonImmutable $today = null): bool
{
    $today ??= CarbonImmutable::now('America/Tijuana')->startOfDay();
    return $this->status === self::STATUS_ACTIVE
        && $this->ends_at !== null
        && $this->ends_at->toDateString() < $today->toDateString();
}
```

Filter index: `status=expired` virtual (active + ends_at < today).

- [ ] **Step 1: Failing test** — contract active ends yesterday → see “Vencido”
- [ ] **Step 2: Implement**
- [ ] **Step 3: Tests + pint**
- [ ] **Step 4: Commit** (si aplica)

---

### Task 8: Docs + Show ledger polish + regression

**Files:**
- Modify: `docs/AI_ONBOARDING.md` (renovación, `DEPOSIT_TRANSFER_OUT`, PDF, envío)
- Modify: Show ledger status labels for transfer out (“Transferencia de depósito”)
- Run broader filters:

```bash
./vendor/bin/sail test --filter='RenewContract|DepositBalance|LeaseAgreement|ContractAgreement|ContractExpired|OrganizationSettings'
./vendor/bin/sail pint --dirty
```

- [ ] **Step 1: Update AI_ONBOARDING**
- [ ] **Step 2: Manual smoke checklist in PR notes** — renew contract #3-like after clearing debts; verify August RENT; PDF; email/WhatsApp
- [ ] **Step 3: Final pint + targeted tests**

---

## Spec coverage check

| Spec requirement | Task |
|------------------|------|
| Nuevo contrato + meta links | 2 |
| End old without settlement | 2 |
| Block outstanding | 2, 6 |
| `DEPOSIT_TRANSFER_OUT` + new HOLD | 1, 2 |
| Deposit difference + register option | 2, 6 |
| RENT current month via hooks | 2 |
| PDF DomPDF Blade | 4 |
| Landlord settings | 3 |
| Email + WhatsApp | 5, 6 |
| Badge Vencido | 7 |
| AI_ONBOARDING | 8 |
| Exclude transfer from operating income | 1 (whitelist types already exclude deposits; ensure Show/overdue/snapshot lists updated) |

## Self-review notes

- Sign of transfer out: **negative** (locked).
- Outstanding must exclude transfer out (Task 1) — critical.
- Transferred HOLD without deposit receipt folio; difference uses `RegisterDepositHoldAction`.
- PDF requires `landlord_name` — wizard should surface Settings link if missing (Task 6).
