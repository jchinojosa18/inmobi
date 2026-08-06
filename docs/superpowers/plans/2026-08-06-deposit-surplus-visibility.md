# Deposit Surplus Visibility Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show deposit surplus to refund before settlement confirmation, and after settlement show surplus/refund with a link to the expense; badge + contract link on Expenses for `REEMBOLSO DEPÓSITO`.

**Architecture:** UI-only. Preview formulas live in `SettlementWizard::render` (same math as `ProcessContractSettlementAction`). Post-settlement uses existing `refundedDepositAmount` + `meta.settlements.*.refund_expense_id`. Expenses list adds badge/link and optional `contractFilter` query string. No Action or migration changes.

**Tech Stack:** Laravel 11, Livewire 4, Blade, PHPUnit via Sail, Pint.

## Global Constraints

- Run all commands via `./vendor/bin/sail` (never bare `php artisan` / `phpunit` / `pint`).
- Diff mínimo: wizard Blade/PHP, expenses Index Blade/PHP, i18n, Feature tests, one line in `AI_ONBOARDING` §4.3. Do **not** change `ProcessContractSettlementAction` or deposit math in `DepositBalanceService`.
- Spec: `docs/superpowers/specs/2026-08-06-deposit-surplus-visibility-design.md`.
- Tests: `./vendor/bin/sail test --filter=SettlementWizardSurplus` and `--filter=ExpensesDepositRefund`; format: `./vendor/bin/sail pint --dirty`.
- No commit unless the user explicitly asks (repo user rule). Skip plan “Commit” steps until asked.

---

## File Map

| File | Responsibility |
|------|----------------|
| `app/Livewire/Contracts/SettlementWizard.php` | Compute `estimatedRefund`, resolve `refundExpenseId` / expenses URL |
| `resources/views/livewire/contracts/settlement-wizard.blade.php` | Preview + post-settlement surplus lines + link |
| `lang/es/contracts.php` / `lang/en/contracts.php` | Surplus / link copy |
| `app/Livewire/Expenses/Index.php` | `contractFilter` query + where clause; clearFilters |
| `resources/views/livewire/expenses/index.blade.php` | Badge + contract link |
| `lang/es/finance.php` / `lang/en/finance.php` | Badge + contract link labels |
| `tests/Feature/Contracts/SettlementWizardSurplusTest.php` | Preview + ended surplus |
| `tests/Feature/Expenses/ExpensesDepositRefundVisibilityTest.php` | Badge, link, filter |
| `docs/AI_ONBOARDING.md` | §4.3 visibility note |

---

### Task 1: Preview “Sobrante a devolver” in SettlementWizard

**Files:**
- Modify: `app/Livewire/Contracts/SettlementWizard.php`
- Modify: `resources/views/livewire/contracts/settlement-wizard.blade.php`
- Modify: `lang/es/contracts.php`
- Modify: `lang/en/contracts.php`
- Create: `tests/Feature/Contracts/SettlementWizardSurplusTest.php`

**Interfaces:**
- Consumes: `DepositBalanceService::availableDepositAmount`, `outstandingBalanceExcludingDepositHold`; public `$concepts` on wizard
- Produces: view vars `estimatedRefund` (float), `estimatedBalanceToCollect` (float); i18n key `contracts.deposit_surplus_to_refund`

- [ ] **Step 1: Write the failing Feature test**

Create `tests/Feature/Contracts/SettlementWizardSurplusTest.php`:

```php
<?php

namespace Tests\Feature\Contracts;

use App\Livewire\Contracts\SettlementWizard;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettlementWizardSurplusTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_shows_surplus_when_exit_concepts_less_than_available_deposit(): void
    {
        [$user, $contract] = $this->makeContractWithUser(depositAmount: 7500.0);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-08',
            'charge_date' => '2026-08-01',
            'amount' => 7500,
        ]);

        Livewire::actingAs($user)
            ->test(SettlementWizard::class, ['contract' => $contract])
            ->set('concepts.0.description', 'salida')
            ->set('concepts.0.amount', '5000')
            ->assertSee(__('contracts.deposit_surplus_to_refund'))
            ->assertSee('$2,500.00');
    }

    public function test_preview_shows_zero_surplus_when_exit_covers_deposit(): void
    {
        [$user, $contract] = $this->makeContractWithUser(depositAmount: 7500.0);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-08',
            'charge_date' => '2026-08-01',
            'amount' => 7500,
        ]);

        Livewire::actingAs($user)
            ->test(SettlementWizard::class, ['contract' => $contract])
            ->set('concepts.0.description', 'salida')
            ->set('concepts.0.amount', '7500')
            ->assertSee(__('contracts.deposit_surplus_to_refund'))
            ->assertSee('$0.00');
    }

    /**
     * @return array{0: User, 1: Contract}
     */
    private function makeContractWithUser(float $depositAmount): array
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
            'deposit_amount' => $depositAmount,
            'rent_amount' => 0,
            'status' => Contract::STATUS_ACTIVE,
        ]);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($user);

        return [$user, $contract];
    }
}
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
./vendor/bin/sail test --filter=SettlementWizardSurplusTest
```

Expected: FAIL — missing translation key and/or `$2,500.00` not in preview context tied to surplus label.

- [ ] **Step 3: Add i18n keys**

In `lang/es/contracts.php` (near `deposit_refunded` / `available`):

```php
'deposit_surplus_to_refund' => 'Sobrante a devolver',
'deposit_surplus_refunded' => 'Sobrante / devolución',
'view_deposit_refund_expense' => 'Ver gasto de devolución',
```

In `lang/en/contracts.php`:

```php
'deposit_surplus_to_refund' => 'Deposit surplus to refund',
'deposit_surplus_refunded' => 'Surplus / refund',
'view_deposit_refund_expense' => 'View refund expense',
```

- [ ] **Step 4: Compute preview in `SettlementWizard::render`**

After loading `$contract` and deposit service amounts, before `return view(...)`:

```php
$conceptsTotal = round(collect($this->concepts)
    ->sum(function (array $row): float {
        $description = trim((string) ($row['description'] ?? ''));
        $amount = (float) ($row['amount'] ?? 0);

        return ($description !== '' && $amount > 0) ? $amount : 0.0;
    }), 2);

$availableDeposit = $depositBalanceService->availableDepositAmount($contract);
$currentOutstanding = $depositBalanceService->outstandingBalanceExcludingDepositHold($contract);
$projectedOutstanding = round($currentOutstanding + $conceptsTotal, 2);
$estimatedRefund = round(max(0, $availableDeposit - $projectedOutstanding), 2);
$estimatedBalanceToCollect = round(max(0, $projectedOutstanding - $availableDeposit), 2);
```

Pass to the view (reuse vars already named where possible):

```php
'availableDeposit' => $availableDeposit,
'currentOutstanding' => $currentOutstanding,
'estimatedRefund' => $estimatedRefund,
'estimatedBalanceToCollect' => $estimatedBalanceToCollect,
'refundExpenseUrl' => null,
'refundedDeposit' => $depositBalanceService->refundedDepositAmount($contract),
// ... existing paid/applied ...
```

(Keep existing keys; avoid duplicate service calls.)

- [ ] **Step 5: Show preview line in Blade (active form only)**

Inside the summary box in `settlement-wizard.blade.php`, **after** available / outstanding lines, when `! $isEnded`:

```blade
@if (! $isEnded)
    <p class="{{ $estimatedRefund > 0 ? 'text-emerald-800' : '' }}">
        {{ __('contracts.deposit_surplus_to_refund') }}:
        <strong>${{ number_format($estimatedRefund, 2) }}</strong>
    </p>
@endif
```

- [ ] **Step 6: Run tests — expect PASS**

```bash
./vendor/bin/sail test --filter=SettlementWizardSurplusTest
./vendor/bin/sail pint --dirty
```

Expected: PASS both tests.

- [ ] **Step 7: Commit (only if user asked)**

```bash
git add app/Livewire/Contracts/SettlementWizard.php \
  resources/views/livewire/contracts/settlement-wizard.blade.php \
  lang/es/contracts.php lang/en/contracts.php \
  tests/Feature/Contracts/SettlementWizardSurplusTest.php
git commit -m "$(cat <<'EOF'
Show deposit surplus preview in settlement wizard.

EOF
)"
```

---

### Task 2: Post-settlement surplus label + link to expenses

**Files:**
- Modify: `app/Livewire/Contracts/SettlementWizard.php`
- Modify: `resources/views/livewire/contracts/settlement-wizard.blade.php`
- Modify: `tests/Feature/Contracts/SettlementWizardSurplusTest.php`

**Interfaces:**
- Consumes: `contract.meta.settlements`, `refundedDeposit`; Task 3’s `contractFilter` query name on `expenses.index`
- Produces: view vars `refundExpenseUrl` (`?string`), ended surplus line using `refundedDeposit`

- [ ] **Step 1: Add failing test for ended contract**

Append to `SettlementWizardSurplusTest.php`:

```php
use App\Actions\Expenses\SeedDefaultExpenseCategoriesAction;
use App\Models\Expense;
use App\Models\ExpenseCategory;

public function test_ended_contract_shows_refunded_surplus_and_expense_link(): void
{
    [$user, $contract] = $this->makeContractWithUser(depositAmount: 7500.0);
    app(SeedDefaultExpenseCategoriesAction::class)->execute((int) $contract->organization_id);

    $categoryId = ExpenseCategory::query()
        ->withoutOrganizationScope()
        ->where('organization_id', $contract->organization_id)
        ->where('name', 'REEMBOLSO DEPÓSITO')
        ->value('id');

    $expense = Expense::query()->create([
        'organization_id' => $contract->organization_id,
        'unit_id' => $contract->unit_id,
        'contract_id' => $contract->id,
        'expense_category_id' => $categoryId,
        'spent_at' => '2026-08-06',
        'amount' => 2500,
        'notes' => 'Devolución de depósito por finiquito',
        'meta' => [
            'reason' => 'contract_settlement',
            'contract_id' => $contract->id,
            'settlement_batch_id' => 'batch-test-1',
        ],
    ]);

    $contract->update([
        'status' => Contract::STATUS_ENDED,
        'ends_at' => '2026-08-06',
        'meta' => array_merge($contract->meta ?? [], [
            'settlement_batch_id' => 'batch-test-1',
            'settlements' => [
                'batch-test-1' => [
                    'batch_id' => 'batch-test-1',
                    'deposit_refund' => 2500,
                    'refund_expense_id' => $expense->id,
                    'deposit_applied' => 5000,
                    'deposit_available' => 7500,
                ],
            ],
        ]),
    ]);

    $expectedUrl = route('expenses.index', ['contractFilter' => $contract->id]);

    Livewire::actingAs($user)
        ->test(SettlementWizard::class, ['contract' => $contract->fresh()])
        ->assertSee(__('contracts.deposit_surplus_refunded'))
        ->assertSee('$2,500.00')
        ->assertSee(__('contracts.view_deposit_refund_expense'))
        ->assertSeeHtml(e($expectedUrl));
}
```

If `Expense::query()->create` needs more fillable fields, mirror `RegisterExpenseAction` / factory — check `Expense` `$fillable` and use `Expense::factory()` if one exists with overrides.

- [ ] **Step 2: Run test — expect FAIL**

```bash
./vendor/bin/sail test --filter=test_ended_contract_shows_refunded_surplus
```

Expected: FAIL — missing ended surplus UI / URL.

- [ ] **Step 3: Resolve `refundExpenseUrl` in render**

```php
$refundExpenseUrl = null;
$refundedDeposit = $depositBalanceService->refundedDepositAmount($contract);

if ($refundedDeposit > 0) {
    $settlements = data_get($contract->meta, 'settlements', []);
    $hasRefundExpense = false;
    if (is_array($settlements)) {
        foreach ($settlements as $row) {
            if ((int) data_get($row, 'refund_expense_id', 0) > 0
                || (float) data_get($row, 'deposit_refund', 0) > 0) {
                $hasRefundExpense = true;
                break;
            }
        }
    }

    if ($hasRefundExpense || $refundedDeposit > 0) {
        $refundExpenseUrl = route('expenses.index', [
            'contractFilter' => $contract->id,
        ]);
    }
}
```

Pass `'refundExpenseUrl' => $refundExpenseUrl`.

- [ ] **Step 4: Blade for ended state**

Inside summary box when `$isEnded`:

```blade
@if ($isEnded)
    <p class="{{ $refundedDeposit > 0 ? 'text-emerald-800' : '' }}">
        {{ __('contracts.deposit_surplus_refunded') }}:
        <strong>${{ number_format($refundedDeposit, 2) }}</strong>
    </p>
    @if ($refundExpenseUrl && $refundedDeposit > 0)
        <p class="mt-1">
            <a href="{{ $refundExpenseUrl }}" class="font-medium text-sky-700 underline">
                {{ __('contracts.view_deposit_refund_expense') }}
            </a>
        </p>
    @endif
@endif
```

Do **not** show `deposit_surplus_to_refund` preview when `$isEnded`.

- [ ] **Step 5: Run tests — expect PASS**

```bash
./vendor/bin/sail test --filter=SettlementWizardSurplusTest
./vendor/bin/sail pint --dirty
```

Expected: all three methods PASS. (Link URL works even before Task 3 filter is wired; route still generates query string.)

- [ ] **Step 6: Commit (only if user asked)**

```bash
git add app/Livewire/Contracts/SettlementWizard.php \
  resources/views/livewire/contracts/settlement-wizard.blade.php \
  tests/Feature/Contracts/SettlementWizardSurplusTest.php
git commit -m "$(cat <<'EOF'
Show post-settlement deposit surplus with link to expenses.

EOF
)"
```

---

### Task 3: Expenses badge, contract link, and `contractFilter`

**Files:**
- Modify: `app/Livewire/Expenses/Index.php`
- Modify: `resources/views/livewire/expenses/index.blade.php`
- Modify: `lang/es/finance.php`
- Modify: `lang/en/finance.php`
- Create: `tests/Feature/Expenses/ExpensesDepositRefundVisibilityTest.php`
- Modify: `docs/AI_ONBOARDING.md` (§4.3)

**Interfaces:**
- Consumes: `Expense.contract_id`, `expenseCategory.name` / `is_system`, `meta.reason`
- Produces: `contractFilter` on query string; badge copy `finance.expenses.deposit_refund_badge`; link `finance.expenses.contract_link`

- [ ] **Step 1: Write failing Feature test**

Create `tests/Feature/Expenses/ExpensesDepositRefundVisibilityTest.php`:

```php
<?php

namespace Tests\Feature\Expenses;

use App\Actions\Expenses\SeedDefaultExpenseCategoriesAction;
use App\Livewire\Expenses\Index as ExpensesIndex;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExpensesDepositRefundVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_deposit_refund_row_shows_badge_and_contract_link(): void
    {
        [$user, $contract, $expense] = $this->makeRefundExpense();

        Livewire::actingAs($user)
            ->test(ExpensesIndex::class)
            ->assertSee(__('finance.expenses.deposit_refund_badge'))
            ->assertSee(__('finance.expenses.contract_link', ['id' => $contract->id]))
            ->assertSeeHtml(route('contracts.show', $contract));
    }

    public function test_contract_filter_limits_expenses_to_that_contract(): void
    {
        [$user, $contract, $expense] = $this->makeRefundExpense();

        $other = Expense::query()->create([
            'organization_id' => $contract->organization_id,
            'unit_id' => null,
            'contract_id' => null,
            'expense_category_id' => $expense->expense_category_id,
            'spent_at' => '2026-08-05',
            'amount' => 100,
            'notes' => 'otro',
            'meta' => [],
        ]);

        Livewire::actingAs($user)
            ->test(ExpensesIndex::class, ['contractFilter' => (string) $contract->id])
            ->assertSee('$2,500.00')
            ->assertDontSee('$100.00');
    }

    /**
     * @return array{0: User, 1: Contract, 2: Expense}
     */
    private function makeRefundExpense(): array
    {
        $organization = Organization::factory()->create();
        app(SeedDefaultExpenseCategoriesAction::class)->execute((int) $organization->id);

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
        ]);
        $user = User::factory()->create(['organization_id' => $organization->id]);

        $categoryId = ExpenseCategory::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organization->id)
            ->where('name', 'REEMBOLSO DEPÓSITO')
            ->value('id');

        $expense = Expense::query()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'contract_id' => $contract->id,
            'expense_category_id' => $categoryId,
            'spent_at' => '2026-08-06',
            'amount' => 2500,
            'notes' => 'Devolución de depósito por finiquito',
            'meta' => [
                'reason' => 'contract_settlement',
                'contract_id' => $contract->id,
            ],
        ]);

        $this->actingAs($user);

        return [$user, $contract, $expense];
    }
}
```

Adjust `Expense::create` fields if the model requires `vendor` / casts — mirror a working create from `RegisterExpenseActionTest`.

- [ ] **Step 2: Run test — expect FAIL**

```bash
./vendor/bin/sail test --filter=ExpensesDepositRefundVisibilityTest
```

Expected: FAIL — missing i18n / filter / badge markup.

- [ ] **Step 3: i18n**

`lang/es/finance.php` under `expenses`:

```php
'deposit_refund_badge' => 'Devolución depósito',
'contract_link' => 'Contrato #:id',
```

`lang/en/finance.php`:

```php
'deposit_refund_badge' => 'Deposit refund',
'contract_link' => 'Contract #:id',
```

- [ ] **Step 4: Add `contractFilter` to `Expenses\Index`**

Property + queryString + clear/hasActive + query:

```php
public string $contractFilter = '';

protected $queryString = [
    // ...existing...
    'contractFilter' => ['except' => ''],
];

public function updatingContractFilter(): void
{
    $this->resetPage();
}

// clearFilters: also
$this->contractFilter = '';

// hasActiveFilters: also
|| $this->contractFilter !== ''

// in render expenses query chain:
->when($this->contractFilter !== '', fn ($query) => $query->where('contract_id', (int) $this->contractFilter))
```

Eager-load already includes `contract.tenant` — keep it.

- [ ] **Step 5: Blade badge + link**

In category cell of `expenses/index.blade.php`:

```blade
<td class="px-4 py-3 font-medium text-slate-900">
    <div class="flex flex-wrap items-center gap-2">
        <span>{{ $expense->expenseCategory?->name ?? '—' }}</span>
        @php
            $isDepositRefund = ($expense->expenseCategory?->name === 'REEMBOLSO DEPÓSITO')
                || data_get($expense->meta, 'reason') === 'contract_settlement';
        @endphp
        @if ($isDepositRefund)
            <span class="rounded bg-emerald-50 px-1.5 py-0.5 text-xs font-medium text-emerald-800">
                {{ __('finance.expenses.deposit_refund_badge') }}
            </span>
        @endif
    </div>
    @if ($expense->contract_id)
        <a href="{{ route('contracts.show', $expense->contract_id) }}" class="mt-1 inline-block text-xs font-medium text-sky-700 underline">
            {{ __('finance.expenses.contract_link', ['id' => $expense->contract_id]) }}
        </a>
    @endif
</td>
```

Spec: show contract link for deposit-refund rows; showing for any expense with `contract_id` is acceptable and simpler (YAGNI). Prefer **only when `$isDepositRefund && $expense->contract_id`** to match design wording.

- [ ] **Step 6: AI_ONBOARDING §4.3**

After the bullet about creating `Expense` on surplus, add:

```markdown
- UI: el panel Finiquito muestra **Sobrante a devolver** (preview) y, tras finiquito, **Sobrante / devolución** con link a Gastos (`?contractFilter=`). En `/expenses`, los reembolsos llevan badge “Devolución depósito” y link al contrato.
```

- [ ] **Step 7: Run all related tests + pint**

```bash
./vendor/bin/sail test --filter=SettlementWizardSurplusTest
./vendor/bin/sail test --filter=ExpensesDepositRefundVisibilityTest
./vendor/bin/sail test --filter=SettlementWizard
./vendor/bin/sail pint --dirty
```

Expected: all PASS.

- [ ] **Step 8: Commit (only if user asked)**

```bash
git add app/Livewire/Expenses/Index.php \
  resources/views/livewire/expenses/index.blade.php \
  lang/es/finance.php lang/en/finance.php \
  tests/Feature/Expenses/ExpensesDepositRefundVisibilityTest.php \
  docs/AI_ONBOARDING.md
git commit -m "$(cat <<'EOF'
Highlight deposit refund expenses and filter by contract.

EOF
)"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Preview surplus before confirm | Task 1 |
| Zero surplus when exit ≥ deposit | Task 1 |
| Ended: Sobrante / devolución + link `?contractFilter=` | Task 2 |
| Expenses badge + contract link | Task 3 |
| `contractFilter` query | Task 3 |
| AI_ONBOARDING note | Task 3 |
| No Action/math changes | Global Constraints |
| Sail tests + pint | Each task |

## Self-review notes

- Query param name locked as `contractFilter` in Tasks 2 and 3.
- i18n keys locked: `deposit_surplus_to_refund`, `deposit_surplus_refunded`, `view_deposit_refund_expense`, `finance.expenses.deposit_refund_badge`, `finance.expenses.contract_link`.
- If `Expense::create` fails validation/fillable in tests, switch to factory + `SeedDefaultExpenseCategoriesAction` pattern from `RegisterExpenseActionTest` without changing production code.
