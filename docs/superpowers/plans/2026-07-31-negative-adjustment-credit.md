# Negative ADJUSTMENT → Credit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convert negative contract `ADJUSTMENT` charges into `credit_balances` at creation (then apply via existing credit flow), fix ledger UI so settled discounts show `paid = amount` / `balance = 0`, and backfill historical orphans.

**Architecture:** New `RegisterContractAdjustmentAction` owns create + credit-on-negative + `ApplyCreditBalanceAction`. Livewire `Contracts\Show` stays thin. Ledger mapping mirrors deposit identity for settled negatives. Idempotent artisan backfill settles legacy rows.

**Tech Stack:** Laravel 11, Livewire 4, PHPUnit via `./vendor/bin/sail`, Pint.

## Global Constraints

- Sail only: `./vendor/bin/sail test`, `./vendor/bin/sail pint --dirty`, `./vendor/bin/sail artisan …`
- Multi-tenant: respect `organization_id`; backfill may use `withoutOrganizationScope()` with explicit org filter.
- Saldo a favor lives in `credit_balances`, not orphan negative charge balances.
- No synthetic discount `Payment`; credit application may still create `method=CREDIT` payments as today.
- Spec: `docs/superpowers/specs/2026-07-31-negative-adjustment-credit-design.md`

## File map

| File | Responsibility |
|------|----------------|
| `app/Actions/Charges/RegisterContractAdjustmentAction.php` | Create ADJUSTMENT; negative → credit + settle meta + apply credit |
| `app/Livewire/Contracts/Show.php` | Delegate create; ledger mapping/status for settled negatives |
| `lang/es/contracts.php`, `lang/en/contracts.php` | `charge_statuses.applied` |
| `app/Console/Commands/SettleNegativeAdjustmentCreditsCommand.php` | Idempotent backfill |
| `tests/Feature/Contracts/ContractShowAdjustmentCreditTest.php` | Replace negative no-op; add pending/apply cases + ledger |
| `tests/Feature/Charges/SettleNegativeAdjustmentCreditsCommandTest.php` | Backfill idempotency |
| `docs/AI_ONBOARDING.md`, `docs/ARCHITECTURE.md` | Document new semantics |

---

### Task 1: `RegisterContractAdjustmentAction` + Livewire wire-up

**Files:**
- Create: `app/Actions/Charges/RegisterContractAdjustmentAction.php`
- Modify: `app/Livewire/Contracts/Show.php` (`createAdjustment`)
- Modify: `tests/Feature/Contracts/ContractShowAdjustmentCreditTest.php`

**Interfaces:**
- Produces: `RegisterContractAdjustmentAction::execute(Contract $contract, float $amount, CarbonImmutable $chargeDate, string $reason, ?string $comment = null, ?string $linkedTo = null, ?int $createdByUserId = null): Charge`
- Consumes: `ApplyCreditBalanceAction::execute(Contract): CreditApplicationResult`, `Charge::query()->create`, `CreditBalance` firstOrNew/restore pattern from `ApplyPaymentAction::registerCredit`

- [ ] **Step 1: Rewrite failing tests for negative semantics**

Replace `test_creating_negative_adjustment_does_not_consume_credit` and add cases in `ContractShowAdjustmentCreditTest.php`:

```php
public function test_creating_negative_adjustment_increases_credit_when_no_pending(): void
{
    [$user, $contract] = $this->makeContractWithCredit(credit: 400.0);

    Livewire::actingAs($user)
        ->test(Show::class, ['contract' => $contract])
        ->set('adjustment_amount', '-100')
        ->set('adjustment_charge_date', '2026-07-15')
        ->set('adjustment_reason', 'Descuento autorizado')
        ->call('createAdjustment')
        ->assertHasNoErrors();

    $adjustment = Charge::query()
        ->where('contract_id', $contract->id)
        ->where('type', Charge::TYPE_ADJUSTMENT)
        ->first();

    $this->assertNotNull($adjustment);
    $this->assertSame(-100.0, (float) $adjustment->amount);
    $this->assertTrue((bool) data_get($adjustment->meta, 'settled_as_credit'));
    $this->assertSame(100.0, (float) data_get($adjustment->meta, 'credit_amount'));

    $this->assertSame(
        500.0,
        (float) CreditBalance::query()->where('contract_id', $contract->id)->value('balance')
    );

    // No pending charges → ApplyCreditBalance is a no-op (no CREDIT payment required).
    $this->assertSame(
        0,
        Payment::query()->where('contract_id', $contract->id)->where('method', Payment::METHOD_CREDIT)->count()
    );
}

public function test_creating_negative_adjustment_applies_credit_to_pending_rent(): void
{
    [$user, $contract] = $this->makeContractWithCredit(credit: 0.0, withUnpaidRent: 1000.0);

    Livewire::actingAs($user)
        ->test(Show::class, ['contract' => $contract])
        ->set('adjustment_amount', '-200')
        ->set('adjustment_charge_date', '2026-07-15')
        ->set('adjustment_reason', 'Condonación parcial')
        ->call('createAdjustment')
        ->assertHasNoErrors();

    $rent = Charge::query()
        ->where('contract_id', $contract->id)
        ->where('type', Charge::TYPE_RENT)
        ->first();

    $this->assertNotNull($rent);
    $allocated = (float) PaymentAllocation::query()->where('charge_id', $rent->id)->sum('amount');
    $this->assertSame(200.0, $allocated);

    $this->assertSame(
        0.0,
        (float) CreditBalance::query()->where('contract_id', $contract->id)->value('balance')
    );
}
```

Extend helper:

```php
/**
 * @return array{0: User, 1: Contract}
 */
private function makeContractWithCredit(float $credit, float $withUnpaidRent = 0.0): array
{
    $organization = Organization::factory()->create();
    $property = Property::factory()->create(['organization_id' => $organization->id]);
    $unit = Unit::factory()->create([
        'organization_id' => $organization->id,
        'property_id' => $property->id,
    ]);
    $tenant = Tenant::factory()->create(['organization_id' => $organization->id]);

    $contractAttrs = [
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'tenant_id' => $tenant->id,
        'rent_amount' => $withUnpaidRent > 0 ? $withUnpaidRent : 0,
    ];

    if ($withUnpaidRent <= 0) {
        $contractAttrs['status'] = Contract::STATUS_ENDED;
        $contractAttrs['ends_at'] = '2026-12-31';
    }

    $contract = Contract::factory()->create($contractAttrs);

    if ($withUnpaidRent > 0) {
        // Ensure a single unpaid RENT exists for the test month (factory/create hooks may already add one).
        $rent = Charge::query()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_RENT)
            ->first();

        if ($rent === null) {
            Charge::query()->create([
                'organization_id' => $organization->id,
                'contract_id' => $contract->id,
                'unit_id' => $unit->id,
                'type' => Charge::TYPE_RENT,
                'period' => '2026-07',
                'rent_period_key' => '2026-07',
                'charge_date' => '2026-07-01',
                'due_date' => '2026-07-15',
                'amount' => $withUnpaidRent,
                'meta' => [],
            ]);
        } else {
            $rent->update(['amount' => $withUnpaidRent]);
        }
    }

    if ($credit > 0 || CreditBalance::query()->where('contract_id', $contract->id)->exists()) {
        CreditBalance::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'contract_id' => $contract->id,
            ],
            ['balance' => $credit]
        );
    } else {
        CreditBalance::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'balance' => 0,
        ]);
    }

    $user = User::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $this->actingAs($user);
    TenantContext::setOrganizationId($organization->id);

    return [$user, $contract];
}
```

Keep `test_creating_positive_adjustment_consumes_credit_balance` unchanged (regression).

- [ ] **Step 2: Run tests — expect fail**

```bash
./vendor/bin/sail test --filter=ContractShowAdjustmentCreditTest
```

Expected: negative cases FAIL (credit still 400 / rent not allocated).

- [ ] **Step 3: Implement Action**

Create `app/Actions/Charges/RegisterContractAdjustmentAction.php`:

```php
<?php

namespace App\Actions\Charges;

use App\Actions\Payments\ApplyCreditBalanceAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RegisterContractAdjustmentAction
{
    public function __construct(
        private readonly ApplyCreditBalanceAction $applyCreditBalanceAction,
    ) {}

    public function execute(
        Contract $contract,
        float $amount,
        CarbonImmutable $chargeDate,
        string $reason,
        ?string $comment = null,
        ?string $linkedTo = null,
        ?int $createdByUserId = null,
    ): Charge {
        $amount = round($amount, 2);

        if ($amount == 0.0) {
            throw new \InvalidArgumentException('Adjustment amount cannot be zero.');
        }

        return DB::transaction(function () use (
            $contract,
            $amount,
            $chargeDate,
            $reason,
            $comment,
            $linkedTo,
            $createdByUserId,
        ): Charge {
            $contract = Contract::query()->lockForUpdate()->findOrFail($contract->id);

            $meta = [
                'reason' => trim($reason),
                'comment' => trim((string) ($comment ?? '')),
                'linked_to' => trim((string) ($linkedTo ?? '')),
                'created_from' => 'contract_show_adjustment',
                'created_by_user_id' => $createdByUserId,
            ];

            /** @var Charge $charge */
            $charge = Charge::query()->create([
                'organization_id' => $contract->organization_id,
                'contract_id' => $contract->id,
                'unit_id' => $contract->unit_id,
                'type' => Charge::TYPE_ADJUSTMENT,
                'period' => $chargeDate->format('Y-m'),
                'charge_date' => $chargeDate->toDateString(),
                'amount' => $amount,
                'meta' => $meta,
            ]);

            if ($amount < 0) {
                $creditAmount = round(abs($amount), 2);
                $this->creditFromAdjustment($contract, $charge, $creditAmount);

                $chargeMeta = is_array($charge->meta) ? $charge->meta : [];
                $chargeMeta['settled_as_credit'] = true;
                $chargeMeta['credit_amount'] = $creditAmount;
                $charge->meta = $chargeMeta;
                $charge->save();
            }

            $this->applyCreditBalanceAction->execute($contract);

            return $charge->refresh();
        }, 3);
    }

    private function creditFromAdjustment(Contract $contract, Charge $charge, float $amount): void
    {
        $creditBalance = CreditBalance::query()
            ->withTrashed()
            ->firstOrNew([
                'organization_id' => $contract->organization_id,
                'contract_id' => $contract->id,
            ]);

        if ($creditBalance->trashed()) {
            $creditBalance->restore();
        }

        $currentBalance = (float) ($creditBalance->balance ?? 0);
        $creditBalance->balance = round($currentBalance + $amount, 2);
        $creditBalance->meta = [
            'last_source' => 'adjustment_credit',
            'last_amount' => $amount,
            'source_charge_id' => $charge->id,
        ];
        $creditBalance->save();
    }
}
```

- [ ] **Step 4: Wire Livewire**

In `Show::createAdjustment`, replace inline `Charge::query()->create` + `ApplyCreditBalanceAction` with:

```php
try {
    app(RegisterContractAdjustmentAction::class)->execute(
        contract: $this->contract,
        amount: (float) $validated['adjustment_amount'],
        chargeDate: $chargeDate,
        reason: trim((string) $validated['adjustment_reason']),
        comment: $validated['adjustment_comment'] ?? null,
        linkedTo: $validated['adjustment_linked_to'] ?? null,
        createdByUserId: auth()->id(),
    );
} catch (ValidationException $exception) {
    // MonthCloseGuard still throws ValidationException from Charge creating
    ...
}
```

Remove unused direct `ApplyCreditBalanceAction` import if no longer needed in this file.

- [ ] **Step 5: Run tests — expect pass**

```bash
./vendor/bin/sail test --filter=ContractShowAdjustmentCreditTest
./vendor/bin/sail pint --dirty
```

- [ ] **Step 6: Commit**

```bash
git add app/Actions/Charges/RegisterContractAdjustmentAction.php app/Livewire/Contracts/Show.php tests/Feature/Contracts/ContractShowAdjustmentCreditTest.php
git commit -m "$(cat <<'EOF'
Apply negative contract adjustments as credit balance.

EOF
)"
```

---

### Task 2: Ledger UI for settled negative adjustments

**Files:**
- Modify: `app/Livewire/Contracts/Show.php` (`mapChargeToLedgerRow`, `resolveChargeStatus`)
- Modify: `lang/es/contracts.php`, `lang/en/contracts.php`
- Modify: `tests/Feature/Contracts/ContractShowAdjustmentCreditTest.php`

**Interfaces:**
- Consumes: `meta.settled_as_credit` on negative `ADJUSTMENT` from Task 1
- Produces: ledger row `paid = amount`, `balance = 0`, status label `contracts.charge_statuses.applied`

- [ ] **Step 1: Add failing ledger assertion**

```php
public function test_settled_negative_adjustment_ledger_row_has_zero_balance(): void
{
    [$user, $contract] = $this->makeContractWithCredit(credit: 0.0);

    Livewire::actingAs($user)
        ->test(Show::class, ['contract' => $contract])
        ->set('adjustment_amount', '-50')
        ->set('adjustment_charge_date', '2026-07-15')
        ->set('adjustment_reason', 'Descuento')
        ->call('createAdjustment')
        ->assertHasNoErrors()
        ->assertSee(__('contracts.charge_statuses.applied'));

    $component = Livewire::actingAs($user)->test(Show::class, ['contract' => $contract]);
    $groups = $component->viewData('ledgerGroups');
    $row = collect($groups)->flatMap(fn ($g) => $g['rows'])->first(
        fn (array $r): bool => $r['type'] === Charge::TYPE_ADJUSTMENT && $r['amount'] < 0
    );

    $this->assertNotNull($row);
    $this->assertSame(-50.0, $row['amount']);
    $this->assertSame(-50.0, $row['paid']);
    $this->assertSame(0.0, $row['balance']);
    $this->assertSame(__('contracts.charge_statuses.applied'), $row['status_label']);
}
```

- [ ] **Step 2: Run test — expect fail**

```bash
./vendor/bin/sail test --filter=test_settled_negative_adjustment_ledger_row_has_zero_balance
```

- [ ] **Step 3: Implement mapping + i18n**

In `mapChargeToLedgerRow`, after computing `$amount`, before deposit branch:

```php
if (
    $charge->type === Charge::TYPE_ADJUSTMENT
    && $amount < 0
    && (bool) data_get($charge->meta, 'settled_as_credit')
) {
    $paid = $amount;
    $balance = 0.0;
} elseif ($this->isDepositLedgerType($charge->type)) {
    ...
} else {
    ...
}
```

In `resolveChargeStatus`, before `balance <= 0` paid branch:

```php
if (
    $charge->type === Charge::TYPE_ADJUSTMENT
    && (float) $charge->amount < 0
    && (bool) data_get($charge->meta, 'settled_as_credit')
) {
    return ['label' => __('contracts.charge_statuses.applied'), 'tone' => 'blue'];
}
```

Lang:

```php
// lang/es/contracts.php inside charge_statuses
'applied' => 'Aplicado',

// lang/en/contracts.php inside charge_statuses
'applied' => 'Applied',
```

- [ ] **Step 4: Run tests + pint**

```bash
./vendor/bin/sail test --filter=ContractShowAdjustmentCreditTest
./vendor/bin/sail pint --dirty
```

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/Contracts/Show.php lang/es/contracts.php lang/en/contracts.php tests/Feature/Contracts/ContractShowAdjustmentCreditTest.php
git commit -m "$(cat <<'EOF'
Show settled negative adjustments as applied with zero balance.

EOF
)"
```

---

### Task 3: Backfill command

**Files:**
- Create: `app/Console/Commands/SettleNegativeAdjustmentCreditsCommand.php`
- Create: `tests/Feature/Charges/SettleNegativeAdjustmentCreditsCommandTest.php`

**Interfaces:**
- Produces: `php artisan inmo:adjustments:settle-negative-credits {--contract-id=} {--organization-id=}`
- Reuses credit logic: prefer calling a package-private path — either extract `creditFromAdjustment` usage by re-invoking a small internal helper on the Action, or duplicate the credit+settle+apply sequence in the command via a dedicated method on the Action:

Add to `RegisterContractAdjustmentAction`:

```php
public function settleExistingNegativeAdjustment(Charge $charge): void
```

Rules: `type=ADJUSTMENT`, `amount<0`, not already `settled_as_credit`; then credit + mark meta + apply. Command loops candidates.

- [ ] **Step 1: Failing feature test**

```php
<?php

namespace Tests\Feature\Charges;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettleNegativeAdjustmentCreditsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_backfill_settles_negative_adjustment_and_is_idempotent(): void
    {
        $organization = Organization::factory()->create();
        TenantContext::setOrganizationId($organization->id);
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
            'status' => Contract::STATUS_ENDED,
            'ends_at' => '2026-12-31',
            'rent_amount' => 0,
        ]);

        CreditBalance::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'balance' => 198.75,
        ]);

        $charge = Charge::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_ADJUSTMENT,
            'period' => '2026-07',
            'charge_date' => '2026-07-31',
            'amount' => -100.0,
            'meta' => ['reason' => 'legacy discount'],
        ]);

        $this->artisan('inmo:adjustments:settle-negative-credits', [
            '--contract-id' => $contract->id,
        ])->assertSuccessful();

        $charge->refresh();
        $this->assertTrue((bool) data_get($charge->meta, 'settled_as_credit'));
        $this->assertSame(
            298.75,
            (float) CreditBalance::query()->where('contract_id', $contract->id)->value('balance')
        );

        $this->artisan('inmo:adjustments:settle-negative-credits', [
            '--contract-id' => $contract->id,
        ])->assertSuccessful();

        $this->assertSame(
            298.75,
            (float) CreditBalance::query()->where('contract_id', $contract->id)->value('balance')
        );
    }
}
```

- [ ] **Step 2: Run — expect fail (command missing)**

```bash
./vendor/bin/sail test --filter=SettleNegativeAdjustmentCreditsCommandTest
```

- [ ] **Step 3: Implement `settleExistingNegativeAdjustment` + command**

On Action:

```php
public function settleExistingNegativeAdjustment(Charge $charge): bool
{
    return DB::transaction(function () use ($charge): bool {
        $charge = Charge::query()->lockForUpdate()->findOrFail($charge->id);

        if ($charge->type !== Charge::TYPE_ADJUSTMENT) {
            return false;
        }

        if ((float) $charge->amount >= 0) {
            return false;
        }

        if ((bool) data_get($charge->meta, 'settled_as_credit')) {
            return false;
        }

        $contract = Contract::query()->lockForUpdate()->findOrFail($charge->contract_id);
        $creditAmount = round(abs((float) $charge->amount), 2);
        $this->creditFromAdjustment($contract, $charge, $creditAmount);

        $meta = is_array($charge->meta) ? $charge->meta : [];
        $meta['settled_as_credit'] = true;
        $meta['credit_amount'] = $creditAmount;
        $charge->meta = $meta;
        $charge->save();

        $this->applyCreditBalanceAction->execute($contract);

        return true;
    }, 3);
}
```

Command sketch:

```php
protected $signature = 'inmo:adjustments:settle-negative-credits
    {--contract-id= : Limita a un contract_id}
    {--organization-id= : Limita a una organization_id}';

protected $description = 'Convierte ADJUSTMENT negativos huérfanos en credit_balances (idempotente)';

public function handle(RegisterContractAdjustmentAction $action): int
{
    $query = Charge::query()
        ->withoutOrganizationScope()
        ->where('type', Charge::TYPE_ADJUSTMENT)
        ->where('amount', '<', 0)
        ->whereNull('deleted_at')
        ->where(function ($q): void {
            $q->whereNull('meta->settled_as_credit')
                ->orWhere('meta->settled_as_credit', false);
        })
        ->orderBy('id');

    if (is_numeric($this->option('contract-id'))) {
        $query->where('contract_id', (int) $this->option('contract-id'));
    }
    if (is_numeric($this->option('organization-id'))) {
        $query->where('organization_id', (int) $this->option('organization-id'));
    }

    $settled = 0;
    $skipped = 0;

    foreach ($query->cursor() as $charge) {
        if ($action->settleExistingNegativeAdjustment($charge)) {
            $settled++;
            $this->line("Settled charge #{$charge->id} contract #{$charge->contract_id}");
        } else {
            $skipped++;
        }
    }

    $this->info("Done. settled={$settled} skipped={$skipped}");

    return self::SUCCESS;
}
```

Note: JSON boolean query on `meta->settled_as_credit` can be flaky across drivers; if tests flake, filter in PHP after fetch (`where amount < 0` only) and rely on Action idempotency.

- [ ] **Step 4: Run tests + pint**

```bash
./vendor/bin/sail test --filter=SettleNegativeAdjustmentCreditsCommandTest
./vendor/bin/sail pint --dirty
```

- [ ] **Step 5: Commit**

```bash
git add app/Actions/Charges/RegisterContractAdjustmentAction.php app/Console/Commands/SettleNegativeAdjustmentCreditsCommand.php tests/Feature/Charges/SettleNegativeAdjustmentCreditsCommandTest.php
git commit -m "$(cat <<'EOF'
Add idempotent backfill for orphan negative adjustments.

EOF
)"
```

---

### Task 4: Docs

**Files:**
- Modify: `docs/AI_ONBOARDING.md` (§4.2 credit invocations + §4.5 adjustments)
- Modify: `docs/ARCHITECTURE.md` (adjustment +/- paragraph)

- [ ] **Step 1: Update AI_ONBOARDING**

In §4.2 list of `ApplyCreditBalanceAction` invocations, clarify that adjustments go through `RegisterContractAdjustmentAction`, and **negative** ADJUSTMENT first credits `credit_balances` (`last_source=adjustment_credit`) then applies.

In §4.5, add:

```markdown
- `ADJUSTMENT` negativo = descuento: se acredita `abs(amount)` en `credit_balances`, se marca `meta.settled_as_credit`, y se aplica crédito a pendientes. Backfill: `inmo:adjustments:settle-negative-credits`.
```

- [ ] **Step 2: Update ARCHITECTURE**

Where adjustments mention `amount (+/-)`, note negativo → crédito aplicado (no saldo de cargo huérfano).

- [ ] **Step 3: Commit**

```bash
git add docs/AI_ONBOARDING.md docs/ARCHITECTURE.md
git commit -m "$(cat <<'EOF'
Document negative adjustment credit semantics.

EOF
)"
```

---

### Task 5: Local verification on contract #3 (manual)

Not a code task — after Tasks 1–3 on the dev DB:

```bash
./vendor/bin/sail artisan inmo:adjustments:settle-negative-credits --contract-id=3
```

Expected: three negative adjustments settled; `credit_balances.balance` ≈ `198.75 + 301.25 = 500.00`; estado de cuenta sin saldo de grupo −301.25; filas “Aplicado” con saldo `$0.00`.

---

## Spec coverage checklist

| Spec requirement | Task |
|---|---|
| Negative → credit + settle meta + ApplyCredit | 1 |
| Positive unchanged | 1 (existing test) |
| Action extracted from Livewire | 1 |
| Ledger paid=amount, balance=0, status Aplicado | 2 |
| Backfill idempotent | 3 |
| Docs AI_ONBOARDING / ARCHITECTURE | 4 |
| Manual contract #3 | 5 |
| No synthetic discount Payment | 1 (by design) |
| Operating income unchanged definition | n/a (no code; credit path existing) |
