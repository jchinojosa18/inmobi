# Cancel contract — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir anular (`status=cancelled`) un contrato creado por error cuando está limpio, con motivo obligatorio, y mostrar bloqueos guiados cuando hay movimientos o mes cerrado.

**Architecture:** Lógica en `CancelContractAction` + DTO `CancelContractEligibility` (reglas únicas para UI y persistencia). Soft-cancel del contrato, soft-delete de cargos abiertos elegibles, `active_lock` liberado por el hook existente del modelo. UI en `Contracts\Show` (modal) y filtro en `Contracts\Index`. Sin hard delete ni edición de inquilino.

**Tech Stack:** Laravel 11, Livewire 4, Blade/Tailwind, PHPUnit via Sail, `MonthCloseGuard`, `AuditLogger`, `TenantContext`.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-08-05-cancel-contract-design.md`.
- Soft cancel: `Contract::STATUS_CANCELLED = 'cancelled'` (distinto de `ended`).
- No hard delete de contrato; no editar `tenant_id` / `unit_id`.
- No auto-revertir pagos ni depósitos; bloqueo + atajos.
- Mes cerrado: bloqueo total (sin bypass Admin); `MonthCloseGuard` al borrar cargos.
- Motivo obligatorio: trim, max 500.
- Permiso: `contracts.manage` (sin permiso nuevo).
- Crédito: columna real `credit_balances.balance` (no `amount`).
- Confirmaciones: `x-ui.confirm-modal` (nunca `wire:confirm` / `window.confirm`).
- Fechas UI: `DateDisplay` / `d/m/Y` si se muestran.
- Sail: `./vendor/bin/sail test --filter=…` y `./vendor/bin/sail pint --dirty`.
- No mezclar commits con cambios ajenos del working tree (p. ej. renew wizard).

## File map

| File | Role |
|------|------|
| `app/Models/Contract.php` | Constante `STATUS_CANCELLED` |
| `app/Actions/Contracts/CancelContractEligibility.php` | DTO readonly `{ allowed, blockers }` |
| `app/Actions/Contracts/CancelContractAction.php` | `evaluate` + `execute` |
| `app/Livewire/Contracts/Show.php` | Estado modal, confirm/cancel/execute |
| `resources/views/livewire/contracts/show.blade.php` | Botón + modal + status anulado |
| `app/Livewire/Contracts/Index.php` | Filtro `cancelled` (ya acepta status string) |
| `resources/views/livewire/contracts/index.blade.php` | Option Anulados |
| `resources/views/livewire/contracts/partials/index-row.blade.php` | Badge anulado |
| `lang/es/contracts.php`, `lang/en/contracts.php` | Copy |
| `tests/Unit/Actions/CancelContractActionTest.php` | Unit action |
| `tests/Feature/Contracts/CancelContractShowTest.php` | Show modal |
| `tests/Feature/Contracts/ContractsIndexTest.php` | Filtro anulados |

---

### Task 1: `CancelContractAction` + eligibility (TDD)

**Files:**
- Create: `app/Actions/Contracts/CancelContractEligibility.php`
- Create: `app/Actions/Contracts/CancelContractAction.php`
- Create: `tests/Unit/Actions/CancelContractActionTest.php`
- Modify: `app/Models/Contract.php` (add `STATUS_CANCELLED`)

**Interfaces:**
- Consumes: `Contract`, `Charge`, `Payment`, `PaymentAllocation`, `CreditBalance`, `MonthClose` / `MonthCloseGuard`, `AuditLogger`, `TenantContext`, SoftDeletes
- Produces:
  - `Contract::STATUS_CANCELLED = 'cancelled'`
  - `CancelContractEligibility` readonly: `bool $allowed`, `array $blockers` where each blocker is `array{code:string,message:string,action_url:?string,action_label:?string}`
  - `CancelContractAction::evaluate(Contract $contract): CancelContractEligibility`
  - `CancelContractAction::execute(Contract $contract, string $reason, ?int $userId): void`
  - Blocker codes: `wrong_status`, `has_payments`, `has_deposit_hold`, `has_allocations`, `has_credit`, `month_closed`, `renewed`

- [ ] **Step 1: Add model constant**

In `app/Models/Contract.php`, after `STATUS_ENDED`:

```php
public const STATUS_CANCELLED = 'cancelled';
```

- [ ] **Step 2: Write failing unit tests**

Create `tests/Unit/Actions/CancelContractActionTest.php`:

```php
<?php

namespace Tests\Unit\Actions;

use App\Actions\Contracts\CancelContractAction;
use App\Actions\Contracts\RegisterDepositHoldAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\MonthClose;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CancelContractActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();
    }

    public function test_cancels_clean_contract_and_soft_deletes_open_rent(): void
    {
        $contract = $this->makeActiveContract();
        TenantContext::setOrganizationId($contract->organization_id);

        $rentIds = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_RENT)
            ->pluck('id')
            ->all();
        $this->assertNotEmpty($rentIds);

        app(CancelContractAction::class)->execute(
            contract: $contract,
            reason: 'Inquilino incorrecto',
            userId: null,
        );

        $fresh = $contract->fresh();
        $this->assertSame(Contract::STATUS_CANCELLED, $fresh->status);
        $this->assertNull($fresh->active_lock);
        $this->assertSame('Inquilino incorrecto', data_get($fresh->meta, 'cancellation_reason'));
        $this->assertNotNull(data_get($fresh->meta, 'cancelled_at'));

        foreach ($rentIds as $id) {
            $this->assertSoftDeleted('charges', ['id' => $id]);
        }

        $this->assertDatabaseHas('audit_events', [
            'action' => 'contract.cancelled',
            'auditable_type' => $contract->getMorphClass(),
            'auditable_id' => $contract->id,
        ]);

        // Unit is free for a new active contract
        $otherTenant = Tenant::factory()->create(['organization_id' => $contract->organization_id]);
        $replacement = Contract::factory()->create([
            'organization_id' => $contract->organization_id,
            'unit_id' => $contract->unit_id,
            'tenant_id' => $otherTenant->id,
            'status' => Contract::STATUS_ACTIVE,
        ]);
        $this->assertSame(Contract::STATUS_ACTIVE, $replacement->status);
        $this->assertSame(1, $replacement->active_lock);
    }

    public function test_blocks_when_payment_exists(): void
    {
        $contract = $this->makeActiveContract();
        TenantContext::setOrganizationId($contract->organization_id);

        Payment::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'amount' => 100,
        ]);

        $eligibility = app(CancelContractAction::class)->evaluate($contract);
        $this->assertFalse($eligibility->allowed);
        $this->assertTrue(collect($eligibility->blockers)->contains(fn ($b) => $b['code'] === 'has_payments'));

        $this->expectException(ValidationException::class);
        app(CancelContractAction::class)->execute($contract, 'motivo', null);
    }

    public function test_blocks_when_deposit_hold_exists(): void
    {
        $contract = $this->makeActiveContract(depositAmount: 1000.0);
        TenantContext::setOrganizationId($contract->organization_id);

        app(RegisterDepositHoldAction::class)->execute(
            contract: $contract,
            amount: 1000.0,
            receivedAt: '2026-08-01',
            notes: null,
            userId: null,
            method: Payment::METHOD_CASH,
        );

        $eligibility = app(CancelContractAction::class)->evaluate($contract);
        $this->assertFalse($eligibility->allowed);
        $this->assertTrue(collect($eligibility->blockers)->contains(fn ($b) => $b['code'] === 'has_deposit_hold'));
    }

    public function test_blocks_when_credit_balance_positive(): void
    {
        $contract = $this->makeActiveContract();
        TenantContext::setOrganizationId($contract->organization_id);

        CreditBalance::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'balance' => 50,
        ]);

        $eligibility = app(CancelContractAction::class)->evaluate($contract);
        $this->assertFalse($eligibility->allowed);
        $this->assertTrue(collect($eligibility->blockers)->contains(fn ($b) => $b['code'] === 'has_credit'));
    }

    public function test_blocks_when_charge_month_is_closed(): void
    {
        $contract = $this->makeActiveContract();
        TenantContext::setOrganizationId($contract->organization_id);

        $rent = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_RENT)
            ->firstOrFail();

        MonthClose::query()->withoutOrganizationScope()->create([
            'organization_id' => $contract->organization_id,
            'month' => $rent->period,
            'closed_at' => now(),
            'closed_by_user_id' => null,
            'snapshot' => [
                'ingresos_operativos' => 0,
                'egresos' => 0,
                'neto' => 0,
                'cartera' => 0,
                'conteos' => [
                    'contratos_activos' => 0,
                    'pagos' => 0,
                    'egresos' => 0,
                ],
            ],
        ]);

        $eligibility = app(CancelContractAction::class)->evaluate($contract);
        $this->assertFalse($eligibility->allowed);
        $this->assertTrue(collect($eligibility->blockers)->contains(fn ($b) => $b['code'] === 'month_closed'));

        $this->expectException(ValidationException::class);
        app(CancelContractAction::class)->execute($contract, 'motivo', null);
    }

    public function test_blocks_when_renewed_to_another_contract(): void
    {
        $contract = $this->makeActiveContract();
        $contract->forceFill([
            'meta' => array_merge($contract->meta ?? [], ['renewed_to_contract_id' => 999]),
        ])->save();

        $eligibility = app(CancelContractAction::class)->evaluate($contract);
        $this->assertFalse($eligibility->allowed);
        $this->assertTrue(collect($eligibility->blockers)->contains(fn ($b) => $b['code'] === 'renewed'));
    }

    public function test_blocks_empty_reason(): void
    {
        $contract = $this->makeActiveContract();
        TenantContext::setOrganizationId($contract->organization_id);

        $this->expectException(ValidationException::class);
        app(CancelContractAction::class)->execute($contract, '   ', null);
    }

    public function test_blocks_ended_and_cancelled_status(): void
    {
        foreach ([Contract::STATUS_ENDED, Contract::STATUS_CANCELLED] as $status) {
            $contract = $this->makeActiveContract(status: $status);
            $eligibility = app(CancelContractAction::class)->evaluate($contract);
            $this->assertFalse($eligibility->allowed, "status {$status}");
            $this->assertTrue(collect($eligibility->blockers)->contains(fn ($b) => $b['code'] === 'wrong_status'));
        }
    }

    public function test_blocks_when_allocations_exist_without_counting_as_clean(): void
    {
        $contract = $this->makeActiveContract();
        TenantContext::setOrganizationId($contract->organization_id);

        $rent = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_RENT)
            ->firstOrFail();

        $payment = Payment::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'amount' => 10,
        ]);
        PaymentAllocation::factory()->create([
            'organization_id' => $contract->organization_id,
            'payment_id' => $payment->id,
            'charge_id' => $rent->id,
            'amount' => 10,
        ]);

        $eligibility = app(CancelContractAction::class)->evaluate($contract);
        $this->assertFalse($eligibility->allowed);
        $codes = collect($eligibility->blockers)->pluck('code');
        $this->assertTrue($codes->contains('has_payments') || $codes->contains('has_allocations'));
    }

    private function makeActiveContract(
        float $depositAmount = 0.0,
        string $status = Contract::STATUS_ACTIVE,
    ): Contract {
        $organization = Organization::factory()->create();
        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $tenant = Tenant::factory()->create(['organization_id' => $organization->id]);

        return Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'deposit_amount' => $depositAmount,
            'rent_amount' => 1000,
            'status' => $status,
            'starts_at' => '2026-08-01',
            'ends_at' => '2027-07-31',
        ]);
    }
}
```

Adjust `MonthClose::create` attributes to match the real factory/migration columns used in `tests/Feature/Contracts/ContractRentAutogenerationTest.php` (copy that create payload if fields differ).

- [ ] **Step 3: Run tests — expect FAIL**

```bash
./vendor/bin/sail test --filter=CancelContractActionTest
```

Expected: FAIL (class `CancelContractAction` not found).

- [ ] **Step 4: Implement DTO**

Create `app/Actions/Contracts/CancelContractEligibility.php`:

```php
<?php

namespace App\Actions\Contracts;

final readonly class CancelContractEligibility
{
    /**
     * @param  list<array{code: string, message: string, action_url: ?string, action_label: ?string}>  $blockers
     */
    public function __construct(
        public bool $allowed,
        public array $blockers = [],
    ) {}
}
```

- [ ] **Step 5: Implement Action**

Create `app/Actions/Contracts/CancelContractAction.php` with this behavior:

```php
public function evaluate(Contract $contract): CancelContractEligibility
{
    $blockers = [];

    if ($contract->status !== Contract::STATUS_ACTIVE) {
        $blockers[] = [
            'code' => 'wrong_status',
            'message' => __('contracts.validation.cancel_wrong_status'),
            'action_url' => null,
            'action_label' => null,
        ];

        return new CancelContractEligibility(allowed: false, blockers: $blockers);
    }

    if (data_get($contract->meta, 'renewed_to_contract_id') !== null) {
        $blockers[] = [
            'code' => 'renewed',
            'message' => __('contracts.validation.cancel_renewed'),
            'action_url' => null,
            'action_label' => null,
        ];
    }

    $hasPayments = Payment::query()
        ->withoutOrganizationScope()
        ->where('contract_id', $contract->id)
        ->exists();
    if ($hasPayments) {
        $blockers[] = [
            'code' => 'has_payments',
            'message' => __('contracts.validation.cancel_has_payments'),
            'action_url' => route('contracts.show', $contract), // anchor optional later
            'action_label' => __('contracts.cancel_shortcut_payments'),
        ];
    }

    $hasDepositHold = Charge::query()
        ->withoutOrganizationScope()
        ->where('contract_id', $contract->id)
        ->where('type', Charge::TYPE_DEPOSIT_HOLD)
        ->exists();
    // Also block other deposit/settlement ledger types if present:
    // DEPOSIT_APPLY, DEPOSIT_TRANSFER_OUT, MOVEOUT — same code has_deposit_hold or separate codes with same UX message family.
    if ($hasDepositHold /* || other deposit ledger types */) {
        $blockers[] = [
            'code' => 'has_deposit_hold',
            'message' => __('contracts.validation.cancel_has_deposit'),
            'action_url' => route('contracts.show', $contract).'#deposit-hold',
            'action_label' => __('contracts.cancel_shortcut_deposit'),
        ];
    }

    $hasAllocations = PaymentAllocation::query()
        ->withoutOrganizationScope()
        ->whereIn('charge_id', Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->select('id'))
        ->exists();
    if ($hasAllocations) {
        $blockers[] = [
            'code' => 'has_allocations',
            'message' => __('contracts.validation.cancel_has_allocations'),
            'action_url' => route('contracts.show', $contract),
            'action_label' => __('contracts.cancel_shortcut_payments'),
        ];
    }

    $credit = (float) (CreditBalance::query()
        ->withoutOrganizationScope()
        ->where('contract_id', $contract->id)
        ->value('balance') ?? 0);
    if ($credit > 0) {
        $blockers[] = [
            'code' => 'has_credit',
            'message' => __('contracts.validation.cancel_has_credit'),
            'action_url' => null,
            'action_label' => null,
        ];
    }

    $charges = Charge::query()
        ->withoutOrganizationScope()
        ->where('contract_id', $contract->id)
        ->get();

    foreach ($charges as $charge) {
        $month = is_string($charge->period) && $charge->period !== ''
            ? $charge->period
            : null;
        if ($month !== null && MonthCloseGuard::isMonthClosed((int) $contract->organization_id, $month)) {
            $blockers[] = [
                'code' => 'month_closed',
                'message' => __('contracts.validation.cancel_month_closed', ['month' => $month]),
                'action_url' => null,
                'action_label' => null,
            ];
            break;
        }
    }

    return new CancelContractEligibility(
        allowed: $blockers === [],
        blockers: $blockers,
    );
}

public function execute(Contract $contract, string $reason, ?int $userId): void
{
    $reason = trim($reason);
    if ($reason === '' || mb_strlen($reason) > 500) {
        throw ValidationException::withMessages([
            'reason' => __('contracts.validation.cancel_reason_required'),
        ]);
    }

    DB::transaction(function () use ($contract, $reason, $userId): void {
        $locked = Contract::query()
            ->withoutOrganizationScope()
            ->lockForUpdate()
            ->findOrFail($contract->id);

        $eligibility = $this->evaluate($locked);
        if (! $eligibility->allowed) {
            throw ValidationException::withMessages([
                'cancel' => $eligibility->blockers[0]['message'] ?? __('contracts.validation.cancel_blocked'),
            ]);
        }

        $deletedChargeIds = [];
        $charges = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $locked->id)
            ->lockForUpdate()
            ->get();

        foreach ($charges as $charge) {
            // MonthCloseGuard runs on deleting via Charge model boot
            $deletedChargeIds[] = $charge->id;
            $charge->delete();
        }

        $meta = $locked->meta ?? [];
        $meta['cancelled_at'] = now('America/Tijuana')->toIso8601String();
        $meta['cancellation_reason'] = $reason;
        $meta['cancelled_by_user_id'] = $userId;

        $locked->status = Contract::STATUS_CANCELLED;
        $locked->meta = $meta;
        $locked->save(); // saving hook sets active_lock = null

        $this->auditLogger->log(
            action: 'contract.cancelled',
            auditable: $locked,
            summary: sprintf('Contrato #%d anulado: %s', $locked->id, $reason),
            meta: [
                'contract_id' => $locked->id,
                'cancellation_reason' => $reason,
                'deleted_charge_ids' => $deletedChargeIds,
            ],
            actorUserId: $userId,
        );
    }, 3);
}
```

Notes for implementer:
- Inject `AuditLogger` in constructor (mirror `VoidDepositHoldAction`).
- Add temporary lang keys in Step 5 only if tests need them; Task 2 formalizes all strings. Prefer adding the validation keys in Task 1 so unit tests don’t blow up on missing translations (Laravel returns the key string — OK for assert on exception message if you assert exception type only).
- When status is `ended`/`cancelled`, `makeActiveContract(status: …)` must set `active_lock` correctly via model saving hook.
- For `STATUS_CANCELLED` factory create: factory may force `active_lock => 1` — override or refresh after save. Prefer creating as `active` then updating status in the ended/cancelled test if factory fights you.
- Block `DEPOSIT_APPLY`, `DEPOSIT_TRANSFER_OUT`, `MOVEOUT` the same as deposit hold (non-trashed).
- `#deposit-hold`: add `id="deposit-hold"` on the deposit panel wrapper in Task 3 if missing.

- [ ] **Step 6: Run unit tests — expect PASS**

```bash
./vendor/bin/sail test --filter=CancelContractActionTest
```

Expected: PASS.

- [ ] **Step 7: Pint + commit**

```bash
./vendor/bin/sail pint --dirty
git add app/Models/Contract.php app/Actions/Contracts/CancelContractEligibility.php app/Actions/Contracts/CancelContractAction.php tests/Unit/Actions/CancelContractActionTest.php lang/es/contracts.php lang/en/contracts.php
git commit -m "$(cat <<'EOF'
Add CancelContractAction for soft-cancelling clean contracts.

EOF
)"
```

Only stage lang files if you added keys in this task.

---

### Task 2: i18n (es/en)

**Files:**
- Modify: `lang/es/contracts.php`
- Modify: `lang/en/contracts.php`

**Interfaces:**
- Consumes: keys referenced by Action + Show/Index
- Produces: all `contracts.*` strings listed below

- [ ] **Step 1: Add Spanish keys** in `lang/es/contracts.php` (near status keys):

```php
'status_cancelled' => 'Anulados',
'status_cancelled_label' => 'Anulado',
'cancel_contract' => 'Anular contrato',
'cancel_contract_title' => 'Anular contrato',
'cancel_contract_help' => 'Esto libera la unidad para crear un contrato nuevo. No es un finiquito: solo úsalo si el contrato se capturó por error y aún no hay pagos ni depósito.',
'cancel_reason' => 'Motivo',
'cancel_reason_placeholder' => 'Ej. Inquilino incorrecto',
'cancel_confirm' => 'Anular contrato',
'cancel_blocked_title' => 'No se puede anular este contrato',
'cancel_shortcut_payments' => 'Ver cobranza del contrato',
'cancel_shortcut_deposit' => 'Ir a depósitos',
'flash' => array_merge(/* if flash is nested, add key inside existing flash array: */),
// Prefer existing structure: if there is 'flash' => [...], add:
// 'contract_cancelled' => 'Contrato anulado. Ya puedes crear uno nuevo en la unidad.',
'validation' => [
    // merge into existing validation array:
    'cancel_wrong_status' => 'Solo se pueden anular contratos activos.',
    'cancel_renewed' => 'Este contrato ya fue renovado y no se puede anular.',
    'cancel_has_payments' => 'Hay pagos registrados. Regularízalos antes de anular.',
    'cancel_has_deposit' => 'Hay un depósito registrado. Anúlalo primero si fue un error.',
    'cancel_has_allocations' => 'Hay cargos con pagos aplicados. No se puede anular todavía.',
    'cancel_has_credit' => 'Hay saldo a favor en el contrato. Regularízalo antes de anular.',
    'cancel_month_closed' => 'Hay cargos en un mes cerrado (:month). No se puede anular.',
    'cancel_reason_required' => 'El motivo de anulación es obligatorio (máx. 500 caracteres).',
    'cancel_blocked' => 'No se puede anular este contrato.',
],
```

Inspect file structure first — merge into existing `'flash'` / `'validation'` arrays; do not nest a second `'validation'` key.

- [ ] **Step 2: Add English equivalents** in `lang/en/contracts.php` with the same keys.

- [ ] **Step 3: Commit**

```bash
git add lang/es/contracts.php lang/en/contracts.php
git commit -m "$(cat <<'EOF'
Add cancel-contract copy in es/en.

EOF
)"
```

---

### Task 3: Show UI — cancel button + modal

**Files:**
- Modify: `app/Livewire/Contracts/Show.php`
- Modify: `resources/views/livewire/contracts/show.blade.php`
- Modify: `resources/views/livewire/contracts/deposit-hold-form.blade.php` (add `id="deposit-hold"` on outer card if missing)
- Create: `tests/Feature/Contracts/CancelContractShowTest.php`

**Interfaces:**
- Consumes: `CancelContractAction::evaluate`, `CancelContractAction::execute`, `contracts.manage`
- Produces Livewire API:
  - `bool $showCancelConfirm = false`
  - `string $cancellation_reason = ''`
  - `array $cancelBlockers = []` (same shape as eligibility blockers)
  - `confirmCancelContract(): void`
  - `cancelCancelConfirm(): void`
  - `executeCancelContract(CancelContractAction $action): void`

- [ ] **Step 1: Write failing feature tests**

Create `tests/Feature/Contracts/CancelContractShowTest.php`:

```php
<?php

namespace Tests\Feature\Contracts;

use App\Livewire\Contracts\Show;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CancelContractShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manage_user_sees_cancel_button_on_active_contract(): void
    {
        [$user, $contract] = $this->makeShowGraph();

        Livewire::actingAs($user)
            ->test(Show::class, ['contract' => $contract])
            ->assertSee(__('contracts.cancel_contract'));
    }

    public function test_lectura_does_not_see_cancel_button(): void
    {
        [$user, $contract] = $this->makeShowGraph();
        $user->syncRoles(['Lectura']);

        Livewire::actingAs($user)
            ->test(Show::class, ['contract' => $contract])
            ->assertDontSee(__('contracts.cancel_contract'));
    }

    public function test_cancel_requires_reason(): void
    {
        [$user, $contract] = $this->makeShowGraph();

        Livewire::actingAs($user)
            ->test(Show::class, ['contract' => $contract])
            ->call('confirmCancelContract')
            ->set('cancellation_reason', '')
            ->call('executeCancelContract')
            ->assertHasErrors(['cancellation_reason']);

        $this->assertSame(Contract::STATUS_ACTIVE, $contract->fresh()->status);
    }

    public function test_cancel_success_sets_cancelled_and_flashes(): void
    {
        [$user, $contract] = $this->makeShowGraph();

        Livewire::actingAs($user)
            ->test(Show::class, ['contract' => $contract])
            ->call('confirmCancelContract')
            ->set('cancellation_reason', 'Inquilino incorrecto')
            ->call('executeCancelContract')
            ->assertRedirect(route('contracts.index'));

        $this->assertSame(Contract::STATUS_CANCELLED, $contract->fresh()->status);
        $this->assertSame(
            __('contracts.flash.contract_cancelled'),
            session('success')
        );
    }

    public function test_blocked_contract_shows_blockers_and_does_not_cancel(): void
    {
        [$user, $contract] = $this->makeShowGraph();
        Payment::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'amount' => 50,
        ]);

        Livewire::actingAs($user)
            ->test(Show::class, ['contract' => $contract])
            ->call('confirmCancelContract')
            ->assertSet('showCancelConfirm', true)
            ->assertSee(__('contracts.cancel_blocked_title'))
            ->assertSee(__('contracts.validation.cancel_has_payments'));

        $this->assertSame(Contract::STATUS_ACTIVE, $contract->fresh()->status);
    }

    /**
     * @return array{0: User, 1: Contract}
     */
    private function makeShowGraph(): array
    {
        Role::findOrCreate('Lectura', 'web');
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
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
            'status' => Contract::STATUS_ACTIVE,
            'rent_amount' => 1000,
        ]);

        return [$user, $contract];
    }
}
```

- [ ] **Step 2: Run — expect FAIL**

```bash
./vendor/bin/sail test --filter=CancelContractShowTest
```

Expected: FAIL (missing button / methods).

- [ ] **Step 3: Implement Livewire methods** in `Show.php`

Add properties and methods:

```php
public bool $showCancelConfirm = false;

public string $cancellation_reason = '';

/** @var list<array{code: string, message: string, action_url: ?string, action_label: ?string}> */
public array $cancelBlockers = [];

public function confirmCancelContract(): void
{
    if (! (auth()->user()?->can('contracts.manage') ?? false)) {
        abort(403);
    }

    $this->contract->refresh();
    $eligibility = app(CancelContractAction::class)->evaluate($this->contract);
    $this->cancelBlockers = $eligibility->blockers;
    $this->cancellation_reason = '';
    $this->showCancelConfirm = true;
    $this->resetErrorBag();
}

public function cancelCancelConfirm(): void
{
    $this->showCancelConfirm = false;
    $this->cancellation_reason = '';
    $this->cancelBlockers = [];
    $this->resetErrorBag();
}

public function executeCancelContract(CancelContractAction $action): void
{
    if (! (auth()->user()?->can('contracts.manage') ?? false)) {
        abort(403);
    }

    if ($this->cancelBlockers !== []) {
        return;
    }

    $this->validate([
        'cancellation_reason' => ['required', 'string', 'max:500'],
    ], [
        'cancellation_reason.required' => __('contracts.validation.cancel_reason_required'),
    ]);

    try {
        $action->execute(
            contract: $this->contract,
            reason: $this->cancellation_reason,
            userId: auth()->id(),
        );
    } catch (ValidationException $exception) {
        $message = $exception->errors()['cancel'][0]
            ?? $exception->errors()['reason'][0]
            ?? __('contracts.validation.cancel_blocked');
        $this->addError('cancellation_reason', $message);
        $this->contract->refresh();
        $this->cancelBlockers = $action->evaluate($this->contract)->blockers;

        return;
    }

    session()->flash('success', __('contracts.flash.contract_cancelled'));
    $this->redirect(route('contracts.index'), navigate: true);
}
```

Import `CancelContractAction`. Pass nothing extra to `render()` unless the blade needs `canCancel` — button can gate on `$canManageContracts && $contract->status === 'active'`.

- [ ] **Step 4: Blade — button + modal + status**

In `show.blade.php` actions slot (near edit), after manage checks:

```blade
@if ($canManageContracts && $contract->status === 'active')
    <x-ui.button type="button" variant="danger" wire:click="confirmCancelContract">
        {{ __('contracts.cancel_contract') }}
    </x-ui.button>
@endif
```

Update status stat-card value to handle cancelled (before treating non-active as finished):

```blade
:value="$contract->status === 'cancelled'
    ? __('contracts.status_cancelled_label')
    : ($contract->isExpired()
        ? __('contracts.status_expired_label')
        : ($contract->isExpiringSoon()
            ? __('contracts.status_expiring_label')
            : ($contract->status === 'active' ? __('common.active') : __('common.finished'))))"
```

At end of section (before closing), modal:

```blade
@if ($canManageContracts)
    <x-ui.confirm-modal
        :open="$showCancelConfirm"
        :title="$cancelBlockers === [] ? __('contracts.cancel_contract_title') : __('contracts.cancel_blocked_title')"
        :confirm-action="$cancelBlockers === [] ? 'executeCancelContract' : ''"
        cancel-action="cancelCancelConfirm"
        :confirm-label="__('contracts.cancel_confirm')"
        :cancel-label="__('common.cancel')"
        :aria-label="__('contracts.cancel_contract_title')"
        max-width="lg"
    >
        @if ($cancelBlockers !== [])
            <ul class="list-disc space-y-2 pl-5 text-slate-700">
                @foreach ($cancelBlockers as $blocker)
                    <li>
                        <span>{{ $blocker['message'] }}</span>
                        @if (! empty($blocker['action_url']) && ! empty($blocker['action_label']))
                            <a href="{{ $blocker['action_url'] }}" class="ml-1 font-medium text-indigo-600 hover:underline">
                                {{ $blocker['action_label'] }}
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <p class="mb-3 text-slate-700">{{ __('contracts.cancel_contract_help') }}</p>
            <label for="cancellation_reason" class="mb-1 block text-sm font-medium text-slate-700">
                {{ __('contracts.cancel_reason') }} *
            </label>
            <textarea
                id="cancellation_reason"
                wire:model="cancellation_reason"
                rows="3"
                placeholder="{{ __('contracts.cancel_reason_placeholder') }}"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            ></textarea>
            @error('cancellation_reason')
                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        @endif
    </x-ui.confirm-modal>
@endif
```

(No `x-ui.textarea` component in this repo — use native `textarea` with existing input styling.)

Add `id="deposit-hold"` on the deposit hold card root in `deposit-hold-form.blade.php`.

- [ ] **Step 5: Run feature tests — PASS**

```bash
./vendor/bin/sail test --filter=CancelContractShowTest
```

- [ ] **Step 6: Pint + commit**

```bash
./vendor/bin/sail pint --dirty
git add app/Livewire/Contracts/Show.php resources/views/livewire/contracts/show.blade.php resources/views/livewire/contracts/deposit-hold-form.blade.php tests/Feature/Contracts/CancelContractShowTest.php
git commit -m "$(cat <<'EOF'
Add cancel-contract UI on contract show.

EOF
)"
```

---

### Task 4: Index filter + badges for cancelled

**Files:**
- Modify: `resources/views/livewire/contracts/index.blade.php`
- Modify: `resources/views/livewire/contracts/partials/index-row.blade.php`
- Modify: `tests/Feature/Contracts/ContractsIndexTest.php`

**Interfaces:**
- Consumes: `status_filter === 'cancelled'` already mapped by existing `where('contracts.status', $this->status_filter)` branch in `Index.php` when not `all`/`expired`/`expiring`/`attention`
- Produces: option + badge labels

- [ ] **Step 1: Write failing index test**

Append to `ContractsIndexTest.php`:

```php
public function test_status_filter_shows_only_cancelled_contracts(): void
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $this->createContractForOrganization(
        $organization,
        tenantName: 'Tenant Active Keep',
        status: Contract::STATUS_ACTIVE
    );

    $cancelled = $this->createContractForOrganization(
        $organization,
        tenantName: 'Tenant Cancelled Only',
        status: Contract::STATUS_ACTIVE
    );
    $cancelled->forceFill(['status' => Contract::STATUS_CANCELLED])->save();

    $response = $this->actingAs($user)->get(route('contracts.index', [
        'status' => Contract::STATUS_CANCELLED,
    ]));

    $response->assertOk();
    $response->assertSeeText('TENANT CANCELLED ONLY');
    $response->assertDontSeeText('TENANT ACTIVE KEEP');
    $response->assertSee(__('contracts.status_cancelled'));
}
```

Ensure `createContractForOrganization` helper in that test file supports status updates (it already has a `status` param for ended — use forceFill for cancelled after create if factory rejects cancelled).

- [ ] **Step 2: Run — expect FAIL** (option missing / no rows)

```bash
./vendor/bin/sail test --filter=test_status_filter_shows_only_cancelled_contracts
```

- [ ] **Step 3: Add filter option** in `index.blade.php` after ended:

```blade
<option value="cancelled">{{ __('contracts.status_cancelled') }}</option>
```

- [ ] **Step 4: Update badge** in `index-row.blade.php`:

```blade
@php
    $statusLabel = match ($contract->status) {
        'active' => __('common.active'),
        'cancelled' => __('contracts.status_cancelled_label'),
        default => __('common.finished'),
    };
    $statusVariant = match ($contract->status) {
        'active' => 'success',
        'cancelled' => 'danger',
        default => 'neutral',
    };
@endphp
<x-ui.badge :variant="$statusVariant" class="mt-1">
    {{ $statusLabel }}
</x-ui.badge>
```

Keep expired/expiring overlays if the row already special-cases them — only replace the active/finished binary for the base status badge; do not remove expired badges if present above.

Hide “register payment” for cancelled (row already gates `@if ($contract->status === 'active')` — verify it stays).

- [ ] **Step 5: Run index tests**

```bash
./vendor/bin/sail test --filter=ContractsIndexTest
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/views/livewire/contracts/index.blade.php resources/views/livewire/contracts/partials/index-row.blade.php tests/Feature/Contracts/ContractsIndexTest.php
git commit -m "$(cat <<'EOF'
Show cancelled contracts in index filter and badges.

EOF
)"
```

---

### Task 5: Full verification + AI onboarding note

**Files:**
- Modify: `docs/AI_ONBOARDING.md` (short bullet under contracts / domain rules)

**Interfaces:**
- Consumes: completed Tasks 1–4
- Produces: documented cancel rule for future agents

- [ ] **Step 1: Run full related suite**

```bash
./vendor/bin/sail test --filter='CancelContractActionTest|CancelContractShowTest|ContractsIndexTest'
./vendor/bin/sail pint --dirty
```

Expected: all PASS; pint clean.

- [ ] **Step 2: Add onboarding note**

In `docs/AI_ONBOARDING.md`, near contract renewal / settlement section, add:

```markdown
### Anulación de contrato (error de captura)
- Acción: `CancelContractAction` — `status=cancelled` (no es finiquito/`ended`).
- Solo contrato limpio (sin pagos, depósito, allocations, crédito; cargos en mes abierto).
- Motivo obligatorio + auditoría `contract.cancelled`.
- Si hay movimientos o mes cerrado: bloquear con atajos; no auto-revertir ledger.
- UI: botón en `Contracts\Show`; filtro Anulados en index.
```

- [ ] **Step 3: Final commit**

```bash
git add docs/AI_ONBOARDING.md
git commit -m "$(cat <<'EOF'
Document contract cancellation in AI onboarding.

EOF
)"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|---|---|
| Soft cancel `cancelled` vs `ended` | 1 |
| Clean: no payments / deposit / allocations / credit | 1 |
| Month closed blocks | 1 |
| Mandatory reason + meta + audit | 1 |
| Delete open charges (soft) | 1 |
| Liberar `active_lock` / unidad | 1 (model hook) |
| Block renewed_to | 1 |
| Guided blockers + shortcuts | 1 + 3 |
| Show button + confirm modal | 3 |
| Index filter Anulados + badge | 4 |
| `contracts.manage` / Lectura hidden | 3 |
| i18n es/en | 2 |
| No edit tenant / no hard delete / no auto-revert | Global + out of scope |
| AI onboarding | 5 |

## Self-review notes

- Credit field is `balance` (spec said `amount` — plan uses real column).
- `confirm-action=""` hides confirm button when blockers present (`x-ui.confirm-modal`).
- No new RBAC permission.
- No migration required.
