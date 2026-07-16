# Audit Remediation 2026-07-15 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cerrar hallazgos Critical/High del audit: crédito auto-aplicado (opción B), documentos seguros, idempotencia financiera, seguridad media, y Livewire/a11y básico.

**Architecture:** Nueva `ApplyCreditBalanceAction` crea payments `METHOD_CREDIT` y allocations con prioridad compartida (`ChargeAllocationPrioritizer`). Call sites: pago, renta, multa, finiquito. Documentos: allowlist morph + download auth + quitar demo. Constraints DB/txn para RENT/settlement/rebuild. Fixes puntuales de Audit/XSS/email y UI a11y.

**Tech Stack:** Laravel 11, Livewire 4, PHPUnit, Sail (`./vendor/bin/sail`), Pint

**Spec:** `docs/superpowers/specs/2026-07-15-audit-remediation-design.md`

## Global Constraints

- Siempre usar `./vendor/bin/sail` para artisan, test, pint (nunca `php artisan` suelto).
- No commitear a menos que el usuario lo pida explícitamente en la sesión (omitir pasos Commit si no).
- Diff mínimo; no Larastan; no cambiar prioridad de allocations ni tasas de multa.
- Multi-tenant: respetar `TenantContext` / `OrganizationScopedModel`; tests con `TenantContext::setOrganizationId`.
- Tras cada grupo: `./vendor/bin/sail test --filter=<…>` y `./vendor/bin/sail pint --dirty`.

## File map

| File | Action | Responsibility |
|------|--------|----------------|
| `app/Support/ChargeAllocationPrioritizer.php` | Create | Prioridad + pending charges compartida |
| `app/Actions/Payments/CreditApplicationResult.php` | Create | Result DTO crédito |
| `app/Actions/Payments/ApplyCreditBalanceAction.php` | Create | Consumir crédito → Payment CREDIT + allocations |
| `app/Models/Payment.php` | Modify | `METHOD_CREDIT` |
| `app/Actions/Payments/ApplyPaymentAction.php` | Modify | Usar prioritizer; aplicar crédito antes |
| `app/Actions/Charges/GenerateMonthlyRentChargesAction.php` | Modify | Aplicar crédito post-RENT |
| `app/Actions/Penalties/RunDailyPenaltiesAction.php` | Modify | Aplicar crédito post-PENALTY |
| `app/Actions/Contracts/ProcessContractSettlementAction.php` | Modify | Crédito + guard idempotencia |
| `app/Actions/Payments/RegisterContractPaymentAction.php` | Modify | Una sola transacción |
| `database/migrations/*_add_rent_period_key_to_charges.php` | Create | Unique RENT |
| `app/Models/Charge.php` | Modify | Sync `rent_period_key` si no generated |
| `app/Console/Commands/RebuildPenaltiesCommand.php` | Modify | Abort si allocations |
| `app/Livewire/Documents/Panel.php` | Modify | Allowlist + URL download |
| `app/Http/Controllers/Documents/DownloadController.php` | Create | Descarga auth |
| `routes/web.php` | Modify | Download route; quitar demo |
| `resources/views/document-upload-demo.blade.php` | Delete | Demo |
| `app/Livewire/DocumentUpload.php` | Delete | Demo |
| `.env.example` | Modify | `DOCUMENTS_DISK=local` |
| `app/Livewire/Settings/AuditIndex.php` | Modify | Scope org |
| `resources/views/livewire/settings/plazas-index.blade.php` | Modify | Escape XSS |
| `resources/views/livewire/settings/index.blade.php` | Modify | Escape XSS |
| `app/Livewire/Payments/Show.php` + vista | Modify | Email solo tenant |
| `app/Support/ContractOverdueQuery.php` | Create | SQL mora compartido |
| `app/Livewire/Dashboard/Index.php` | Modify | Usar Support |
| `app/Livewire/Cobranza/Index.php` | Modify | Usar Support |
| `resources/views/components/ui/modal.blade.php` | Modify | Focus trap + labelledby |
| `resources/views/components/ui/input.blade.php` | Modify | Auto id |
| `resources/views/components/ui/select.blade.php` | Modify | Auto id |
| Varias blades de listados | Modify | `wire:key` |
| `docs/AI_ONBOARDING.md` / `ARCHITECTURE.md` | Modify | Documentar crédito B |
| Tests bajo `tests/Unit` y `tests/Feature` | Create/Modify | Cobertura por task |

---

### Task 1: ChargeAllocationPrioritizer + ApplyCreditBalanceAction

**Files:**
- Create: `app/Support/ChargeAllocationPrioritizer.php`
- Create: `app/Actions/Payments/CreditApplicationResult.php`
- Create: `app/Actions/Payments/ApplyCreditBalanceAction.php`
- Modify: `app/Models/Payment.php` (añadir `METHOD_CREDIT = 'CREDIT'`)
- Modify: `app/Actions/Payments/ApplyPaymentAction.php` (delegar prioridad al Support; **aún no** llamar crédito — Task 2)
- Test: `tests/Unit/Actions/ApplyCreditBalanceActionTest.php`

**Interfaces:**
- Produces: `ChargeAllocationPrioritizer::pendingPrioritized(Contract $contract): Collection<int, Charge>`
- Produces: `ApplyCreditBalanceAction::execute(Contract $contract): CreditApplicationResult`
- Produces: `CreditApplicationResult` con `appliedAmount: float`, `allocationsCount: int`, `paymentId: ?int`

- [ ] **Step 1: Write the failing test**

Crear `tests/Unit/Actions/ApplyCreditBalanceActionTest.php` siguiendo el patrón de `ApplyPaymentActionTest` (`makeContractGraph`, `TenantContext`, `RefreshDatabase`):

```php
public function test_it_applies_credit_to_pending_rent_and_decrements_balance(): void
{
    [$organization, $contract, $unit] = $this->makeContractGraph();
    TenantContext::setOrganizationId($organization->id);

    Charge::factory()->create([
        'organization_id' => $organization->id,
        'contract_id' => $contract->id,
        'unit_id' => $unit->id,
        'type' => Charge::TYPE_RENT,
        'amount' => 1000,
        'charge_date' => '2026-01-05',
    ]);

    CreditBalance::query()->create([
        'organization_id' => $organization->id,
        'contract_id' => $contract->id,
        'balance' => 400,
    ]);

    $result = app(ApplyCreditBalanceAction::class)->execute($contract);

    $this->assertSame(400.0, $result->appliedAmount);
    $this->assertSame(1, $result->allocationsCount);
    $this->assertNotNull($result->paymentId);

    $this->assertDatabaseHas('payments', [
        'id' => $result->paymentId,
        'method' => Payment::METHOD_CREDIT,
        'amount' => '400.00',
    ]);
    $this->assertSame(0.0, (float) CreditBalance::query()->where('contract_id', $contract->id)->value('balance'));
}

public function test_it_is_noop_when_credit_is_zero(): void
{
    [$organization, $contract] = $this->makeContractGraph();
    TenantContext::setOrganizationId($organization->id);

    $result = app(ApplyCreditBalanceAction::class)->execute($contract);

    $this->assertSame(0.0, $result->appliedAmount);
    $this->assertNull($result->paymentId);
    $this->assertDatabaseCount('payments', 0);
}
```

Copiar `makeContractGraph()` desde `ApplyPaymentActionTest`.

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/sail test --filter=ApplyCreditBalanceActionTest
```

Expected: FAIL (clase no existe).

- [ ] **Step 3: Implement prioritizer + action**

`ChargeAllocationPrioritizer`: mover lógica de `prioritizedPendingCharges` / `priorityRank` / `isRefundableService` desde `ApplyPaymentAction`.

`ApplyCreditBalanceAction` (esqueleto):

```php
public function execute(Contract $contract): CreditApplicationResult
{
    return DB::transaction(function () use ($contract): CreditApplicationResult {
        $contract = Contract::query()->lockForUpdate()->findOrFail($contract->id);

        $credit = CreditBalance::query()
            ->where('contract_id', $contract->id)
            ->lockForUpdate()
            ->first();

        $available = round((float) ($credit?->balance ?? 0), 2);
        if ($available <= 0) {
            return new CreditApplicationResult(0.0, 0, null);
        }

        $charges = app(ChargeAllocationPrioritizer::class)->pendingPrioritized($contract);
        $pendingTotal = round($charges->sum(fn (Charge $c) => (float) $c->amount - (float) ($c->allocated_amount ?? 0)), 2);
        $toApply = round(min($available, $pendingTotal), 2);
        if ($toApply <= 0) {
            return new CreditApplicationResult(0.0, 0, null);
        }

        $payment = Payment::query()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'paid_at' => now(),
            'amount' => $toApply,
            'method' => Payment::METHOD_CREDIT,
            'reference' => null,
            'receipt_folio' => null,
            'meta' => ['source' => 'credit_application'],
        ]);

        // allocate $toApply across $charges (same loop as ApplyPaymentAction)
        // set meta allocation_processed=true, credited_amount=0
        // decrement credit->balance by allocatedAmount
        // return CreditApplicationResult
    }, 3);
}
```

Refactor `ApplyPaymentAction` para usar `ChargeAllocationPrioritizer` (tests existentes de ApplyPayment deben seguir pasando).

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/sail test --filter=ApplyCreditBalanceActionTest
./vendor/bin/sail test --filter=ApplyPaymentActionTest
./vendor/bin/sail pint --dirty
```

Expected: PASS.

- [ ] **Step 5: Commit** (solo si el usuario lo pidió)

```bash
git add app/Support/ChargeAllocationPrioritizer.php app/Actions/Payments/ApplyCreditBalanceAction.php app/Actions/Payments/CreditApplicationResult.php app/Models/Payment.php app/Actions/Payments/ApplyPaymentAction.php tests/Unit/Actions/ApplyCreditBalanceActionTest.php
git commit -m "$(cat <<'EOF'
feat: apply credit balance to pending charges

EOF
)"
```

---

### Task 2: Wire credit into ApplyPaymentAction (cash after credit)

**Files:**
- Modify: `app/Actions/Payments/ApplyPaymentAction.php`
- Test: `tests/Unit/Actions/ApplyPaymentActionTest.php` (añadir caso)

**Interfaces:**
- Consumes: `ApplyCreditBalanceAction::execute`
- Produces: pago cash se aplica **después** de consumir crédito existente

- [ ] **Step 1: Write failing test**

```php
public function test_it_applies_existing_credit_before_cash_payment(): void
{
    [$organization, $contract, $unit] = $this->makeContractGraph();
    TenantContext::setOrganizationId($organization->id);

    $rent = Charge::factory()->create([
        'organization_id' => $organization->id,
        'contract_id' => $contract->id,
        'unit_id' => $unit->id,
        'type' => Charge::TYPE_RENT,
        'amount' => 1000,
        'charge_date' => '2026-02-01',
    ]);

    CreditBalance::query()->create([
        'organization_id' => $organization->id,
        'contract_id' => $contract->id,
        'balance' => 300,
    ]);

    $payment = Payment::factory()->create([
        'organization_id' => $organization->id,
        'contract_id' => $contract->id,
        'amount' => 700,
        'method' => Payment::METHOD_TRANSFER,
    ]);

    app(ApplyPaymentAction::class)->execute($contract, $payment);

    $this->assertSame(0.0, (float) CreditBalance::query()->where('contract_id', $contract->id)->value('balance'));
    $this->assertSame(1000.0, (float) PaymentAllocation::query()->where('charge_id', $rent->id)->sum('amount'));
    $this->assertDatabaseHas('payments', [
        'contract_id' => $contract->id,
        'method' => Payment::METHOD_CREDIT,
        'amount' => '300.00',
    ]);
}
```

- [ ] **Step 2: Run — expect FAIL** (crédito no se consume)

```bash
./vendor/bin/sail test --filter=test_it_applies_existing_credit_before_cash_payment
```

- [ ] **Step 3: Implement**

Al inicio del body de `ApplyPaymentAction::execute` (después de locks e idempotency check, **antes** del loop de allocation del payment cash):

```php
app(ApplyCreditBalanceAction::class)->execute($contract);
// re-lock / refresh charges vía prioritizer en el loop siguiente
```

Inyectar por constructor o `app()` consistente con el codebase.

- [ ] **Step 4: Run full payment tests + pint**

```bash
./vendor/bin/sail test --filter=ApplyPaymentActionTest
./vendor/bin/sail pint --dirty
```

- [ ] **Step 5: Commit** (si pedido)

---

### Task 3: Wire credit into rent generation + penalties

**Files:**
- Modify: `app/Actions/Charges/GenerateMonthlyRentChargesAction.php`
- Modify: `app/Actions/Penalties/RunDailyPenaltiesAction.php`
- Test: `tests/Unit/Actions/GenerateMonthlyRentChargesActionTest.php` (crear o extender)
- Test: extender test de penalties existente si hay; si no, `tests/Unit/Actions/RunDailyPenaltiesCreditTest.php`

**Interfaces:**
- Consumes: `ApplyCreditBalanceAction::execute(Contract)`
- Tras crear/asegurar RENT o crear PENALTY → aplicar crédito al contrato

- [ ] **Step 1: Write failing tests**

Rent: crédito 500, generar RENT 1000 → allocation CREDIT 500, balance 0, charge partial.

Penalty: RENT unpaid + crédito; tras crear penalty, crédito se aplica según prioridad (RENT primero).

- [ ] **Step 2: Run — expect FAIL**

```bash
./vendor/bin/sail test --filter=GenerateMonthlyRentCharges
./vendor/bin/sail test --filter=RunDailyPenaltiesCredit
```

- [ ] **Step 3: Implement**

En `createRentChargeForContractPeriod`, después de `firstOrCreate`, siempre:

```php
app(ApplyCreditBalanceAction::class)->execute($contract);
return $charge;
```

En `RunDailyPenaltiesAction`, inmediatamente después de persistir el cargo PENALTY exitosamente:

```php
app(ApplyCreditBalanceAction::class)->execute($contract);
```

(Usar el mismo `$contract` locked/scoped del loop.)

- [ ] **Step 4: Run + pint**

```bash
./vendor/bin/sail test --filter=GenerateMonthlyRentCharges
./vendor/bin/sail test --filter=Penalty
./vendor/bin/sail pint --dirty
```

- [ ] **Step 5: Commit** (si pedido)

---

### Task 4: Settlement — credit first + idempotency guard

**Files:**
- Modify: `app/Actions/Contracts/ProcessContractSettlementAction.php`
- Modify: `app/Livewire/Contracts/SettlementWizard.php` (o equivalente) — bloquear si `ended`
- Test: `tests/Unit/Actions/ProcessContractSettlementActionTest.php` (extender/crear)

**Interfaces:**
- Consumes: `ApplyCreditBalanceAction` antes de `outstandingBalanceExcludingDepositHold`
- Abort si `status === ended` o meta ya tiene settlement

- [ ] **Step 1: Write failing tests**

1. Contrato ended → segunda ejecución lanza excepción.
2. Crédito cubre outstanding → `depositApplied` no inventa cobro / no sobre-aplica.

- [ ] **Step 2: Run — expect FAIL**

```bash
./vendor/bin/sail test --filter=ProcessContractSettlement
```

- [ ] **Step 3: Implement**

Al inicio del transaction, tras lock:

```php
if ($lockedContract->status === Contract::STATUS_ENDED) {
    throw new RuntimeException('Contract already settled/ended.');
}
if (data_get($lockedContract->meta, 'settlement_batch_id')) {
    throw new RuntimeException('Settlement already recorded for this contract.');
}

app(ApplyCreditBalanceAction::class)->execute($lockedContract);

$outstandingBeforeDeposit = $this->depositBalanceService->outstandingBalanceExcludingDepositHold($lockedContract->fresh());
```

Al terminar, persistir `settlement_batch_id` en `meta` del contrato (si aún no se hace).

UI: si contrato ended, no mostrar wizard / abort en mount.

- [ ] **Step 4: Run + pint**

```bash
./vendor/bin/sail test --filter=ProcessContractSettlement
./vendor/bin/sail pint --dirty
```

- [ ] **Step 5: Commit** (si pedido)

---

### Task 5: Documents allowlist + remove demo + auth download

**Files:**
- Modify: `app/Livewire/Documents/Panel.php`
- Create: `app/Http/Controllers/Documents/DownloadController.php`
- Modify: `routes/web.php` (ruta download; eliminar demo)
- Delete: `resources/views/document-upload-demo.blade.php`, `app/Livewire/DocumentUpload.php`, `resources/views/livewire/document-upload.blade.php` (si solo demo)
- Modify: `.env.example` → `DOCUMENTS_DISK=local`
- Test: `tests/Feature/Documents/DocumentSecurityTest.php`

**Interfaces:**
- Allowlist FQCN: `Contract`, `Payment`, `Expense`, `Unit`, `Charge`
- `GET documents/{document}/download` → `permission:documents.view`

- [ ] **Step 1: Write failing tests**

```php
public function test_demo_upload_route_is_gone(): void
{
    $this->get('/demo/document-upload')->assertNotFound();
}

public function test_panel_rejects_user_morph(): void
{
    // actingAs user with documents.upload; Livewire::test(Panel::class, [
    //   'documentableType' => User::class, 'documentableId' => $other->id
    // ])->call('save')->assertForbidden() or status 404
}

public function test_download_requires_same_org(): void
{
    // document de otra org → 403/404
}
```

- [ ] **Step 2: Run — expect FAIL** (demo aún 200)

```bash
./vendor/bin/sail test --filter=DocumentSecurityTest
```

- [ ] **Step 3: Implement**

`resolveDocumentable`:

```php
private const ALLOWED = [
    Contract::class,
    Payment::class,
    Expense::class,
    Unit::class,
    Charge::class,
];

private function resolveDocumentable(): Model
{
    if (! in_array($this->documentableType, self::ALLOWED, true)) {
        abort(404);
    }
    $orgId = (int) auth()->user()->organization_id;
    $model = $this->documentableType::query()->findOrFail($this->documentableId);
    if ((int) $model->getAttribute('organization_id') !== $orgId) {
        abort(403);
    }
    return $model;
}
```

DownloadController: cargar `Document` scoped, stream desde disk en `meta.disk` o config.

Panel `url` → `route('documents.download', $document)`.

Eliminar ruta demo en `routes/web.php`.

- [ ] **Step 4: Run + pint**

```bash
./vendor/bin/sail test --filter=DocumentSecurityTest
./vendor/bin/sail pint --dirty
```

- [ ] **Step 5: Commit** (si pedido)

---

### Task 6: Unique RENT `rent_period_key`

**Files:**
- Create: `database/migrations/2026_07_16_000001_add_rent_period_key_to_charges_table.php`
- Modify: `app/Models/Charge.php` (boot `saving` setea key si columna no generated en SQLite)
- Modify: `app/Actions/Charges/GenerateMonthlyRentChargesAction.php` (catch duplicate)
- Test: `tests/Unit/Actions/GenerateMonthlyRentChargesActionTest.php` (idempotencia fuerte)

- [ ] **Step 1: Write failing test** — dos `firstOrCreate` / create concurrente → un solo RENT; segundo create con mismo period falla o skips.

- [ ] **Step 2: Run — expect FAIL** (sin unique)

- [ ] **Step 3: Migration**

1. Soft-delete RENTs duplicados `(contract_id, period)` sin allocations (keep min id); si hay allocations en duplicados → `throw` en migración.
2. Añadir `rent_period_key` string(7) nullable.
3. Backfill: `UPDATE charges SET rent_period_key = period WHERE type = 'RENT'`.
4. Unique index `(contract_id, rent_period_key)`.
5. En `Charge::saving`: si `type===RENT` set `rent_period_key=period`, else `null`.

Catch `QueryException` duplicate en `createRentChargeForContractPeriod` → re-fetch existing.

- [ ] **Step 4: Run migrate + tests**

```bash
./vendor/bin/sail artisan migrate
./vendor/bin/sail test --filter=GenerateMonthlyRentCharges
./vendor/bin/sail pint --dirty
```

- [ ] **Step 5: Commit** (si pedido)

---

### Task 7: RegisterContractPaymentAction single transaction + rebuild harden

**Files:**
- Modify: `app/Actions/Payments/RegisterContractPaymentAction.php`
- Modify: `app/Console/Commands/RebuildPenaltiesCommand.php`
- Test: feature/unit para rollback; test comando rebuild

- [ ] **Step 1: Tests**

1. `RegisterContractPaymentAction`: si `ApplyPaymentAction` lanza a mitad, no queda payment (usar mock parcial o forzar fallo).
2. Rebuild: charge PENALTY con allocation → comando exit ≠ 0 y charge sigue existiendo.

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Implement**

Envolver `createPaymentWithRetry` + apply + evidence + audit en **una** `DB::transaction` (ajustar retry folio para no romper nesting: folio retry dentro, apply dentro del mismo outer transaction).

Rebuild:

```php
$hasAllocations = DB::table('payment_allocations')
    ->whereIn('charge_id', $penaltyIds)
    ->exists();
if ($hasAllocations) {
    $this->error('Cannot rebuild: penalties have payment allocations.');
    return self::FAILURE;
}
// soft-delete Charge models (Eloquent) en vez de DB::table hard delete
```

No borrar allocations.

- [ ] **Step 4: Run + pint**

```bash
./vendor/bin/sail test --filter=RegisterContractPayment
./vendor/bin/sail test --filter=RebuildPenalt
./vendor/bin/sail pint --dirty
```

- [ ] **Step 5: Commit** (si pedido)

---

### Task 8: Audit IDOR + XSS escape + receipt email tenant-only

**Files:**
- Modify: `app/Livewire/Settings/AuditIndex.php`
- Modify: `resources/views/livewire/settings/plazas-index.blade.php`
- Modify: `resources/views/livewire/settings/index.blade.php`
- Modify: `app/Livewire/Payments/Show.php` + `resources/views/livewire/payments/show.blade.php`
- Tests: Feature correspondientes

- [ ] **Step 1: Failing tests** — audit otra org 404; HTML escapa `<script>`; sendReceipt no acepta email libre.

- [ ] **Step 2: Run FAIL**

- [ ] **Step 3: Implement**

```php
// AuditIndex
AuditEvent::query()
    ->where('organization_id', auth()->user()->organization_id)
    ->findOrFail($this->selectedEventId);
```

Blade: `{{ $pendingDeletePlazaName }}` en lugar de `{!! ... !!}`.

Show: quitar `emailRecipient` editable; usar `$payment->contract->tenant->email`; deshabilitar botón si vacío.

- [ ] **Step 4: Run + pint**

```bash
./vendor/bin/sail test --filter=Audit
./vendor/bin/sail test --filter=PaymentShow
./vendor/bin/sail pint --dirty
```

- [ ] **Step 5: Commit** (si pedido)

---

### Task 9: ContractOverdueQuery + wire:key + a11y inputs/modal

**Files:**
- Create: `app/Support/ContractOverdueQuery.php`
- Modify: `app/Livewire/Dashboard/Index.php`, `app/Livewire/Cobranza/Index.php`
- Modify: `resources/views/components/ui/modal.blade.php`, `input.blade.php`, `select.blade.php`
- Modify blades listados: contracts, cobranza, dashboard, expenses, tenants, properties, settlement-wizard concepts

- [ ] **Step 1: Test** — unit del Support: métodos `statusSql`/`daysSql` retornan strings no vacíos para sqlite y mysql (usar `DB::getDriverName()` como hoy). Smoke Livewire opcional.

- [ ] **Step 2: FAIL**

- [ ] **Step 3: Implement**

Extraer SQL de Dashboard a `ContractOverdueQuery`; Cobranza llama lo mismo.

Input:

```php
$id = $id ?? ($label ? 'input-'.md5($label.spl_object_id($this)) : null);
```

Mejor en Blade:

```blade
@php
    $inputId = $id ?? ($label ? 'field-'.Str::slug($label).'-'.substr(md5($attributes->wire('model')->value() ?? $label), 0, 8) : null);
@endphp
```

Modal: `x-data` + `x-trap.noscroll="true"` (si Alpine plugin disponible) o trap manual; `aria-labelledby` apuntando a id del `h2`.

`wire:key="contract-{{ $contract->id }}"` (y análogos) en cada `@forelse`.

- [ ] **Step 4: Run dashboard/cobranza tests + pint**

```bash
./vendor/bin/sail test --filter=Dashboard
./vendor/bin/sail test --filter=Cobranza
./vendor/bin/sail pint --dirty
```

- [ ] **Step 5: Commit** (si pedido)

---

### Task 10: Docs + verification final

**Files:**
- Modify: `docs/AI_ONBOARDING.md` (§ pagos / crédito opción B + call sites)
- Modify: `docs/ARCHITECTURE.md` (§ CreditBalance)
- Confirm: `.env.example` `DOCUMENTS_DISK=local`

- [ ] **Step 1: Update docs** — describir `ApplyCreditBalanceAction`, METHOD_CREDIT, call sites, documentos privados.

- [ ] **Step 2: Full verification**

```bash
./vendor/bin/sail test --filter=ApplyCreditBalance
./vendor/bin/sail test --filter=ApplyPaymentAction
./vendor/bin/sail test --filter=GenerateMonthlyRent
./vendor/bin/sail test --filter=ProcessContractSettlement
./vendor/bin/sail test --filter=DocumentSecurity
./vendor/bin/sail test --filter=RebuildPenalt
./vendor/bin/sail pint --dirty
```

Opcional smoke financiero:

```bash
./vendor/bin/sail artisan inmo:smoke --date=2026-03-10
```

- [ ] **Step 3: Commit docs** (si pedido)

---

## Spec coverage self-check

| Spec section | Task(s) |
|--------------|---------|
| ApplyCreditBalanceAction + prioritizer | 1 |
| Call site ApplyPaymentAction | 2 |
| Call sites rent + penalties | 3 |
| Settlement credit + B2 | 4 |
| Settlement idempotency B4 | 4 |
| Documents S1–S3 | 5 |
| Unique RENT B3 | 6 |
| Register txn B5 + rebuild B6 | 7 |
| Audit/XSS/email S4–S6 | 8 |
| Livewire/a11y/overdue Q1/L/A | 9 |
| Docs AI_ONBOARDING/ARCHITECTURE | 10 |
| Larastan | Out of scope ✓ |

## Placeholder scan

Sin TBD/TODO vagos; firmas de actions definidas en Task 1; commits opcionales por regla de usuario.
