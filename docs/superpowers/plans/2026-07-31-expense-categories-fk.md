# Expense Categories FK Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Normalize expenses with mandatory `expense_category_id` FK, optional `contract_id`, seeded default categories per organization, and consistent category-based reporting.

**Architecture:** Add schema columns and backfill migration; centralize creation in `RegisterExpenseAction` and category seeding in `SeedDefaultExpenseCategoriesAction`. Livewire forms become thin orchestrators with select-based capture. Settlement and deposit services query by system category + `contract_id` instead of free-text `category`.

**Tech Stack:** Laravel 11, Livewire 4, Tailwind, Spatie Permission, Sail for artisan/test/pint.

## Global Constraints

- Run all commands via `./vendor/bin/sail` (never bare `php artisan`).
- Multi-tenant: models use `OrganizationScopedModel`; validate FKs belong to same `organization_id`.
- Permissions: reuse `expenses.view`, `expenses.create`, `expenses.manage`, `expense_categories.manage`; no new permissions.
- Month closed: `MonthCloseGuard` on `Expense` unchanged (`spent_at`).
- UI dates: display `d/m/Y`; inputs/persistence `Y-m-d`.
- Business logic in `app/Actions`; Livewire validates + delegates.
- Tests: `./vendor/bin/sail test --filter=...`; format: `./vendor/bin/sail pint --dirty`.
- Spec: `docs/superpowers/specs/2026-07-31-expense-categories-design.md`.

---

## File Map

| File | Responsibility |
|------|----------------|
| `database/migrations/2026_07_31_*_add_expense_category_fk_and_contract.php` | Schema + data backfill |
| `app/Actions/Expenses/SeedDefaultExpenseCategoriesAction.php` | Idempotent default categories per org |
| `app/Actions/Expenses/RegisterExpenseAction.php` | Validated expense creation |
| `app/Models/ExpenseCategory.php` | `is_system`, `expenses()` relation, scopes |
| `app/Models/Expense.php` | FK relations, auditable attrs, remove `category` |
| `app/Http/Controllers/Auth/RegisterController.php` | Seed categories on new org |
| `app/Actions/Contracts/ProcessContractSettlementAction.php` | Refund expense via FK |
| `app/Support/DepositBalanceService.php` | Refund lookup via FK |
| `app/Livewire/Expenses/Index.php` | Select UI + Action delegation |
| `app/Livewire/Expenses/QuickRegisterModal.php` | Select UI + Action delegation |
| `resources/views/livewire/expenses/index.blade.php` | Category/unit/contract selects |
| `resources/views/livewire/expenses/quick-register-modal.blade.php` | Same |
| `app/Livewire/Settings/Index.php` | Delete guards for system/in-use categories |
| `app/Livewire/Reports/CashFlow.php` | Expense breakdown by category |
| `resources/views/livewire/reports/cash-flow.blade.php` | Category subtotals |
| `app/Http/Controllers/Reports/CashFlowCsvExportController.php` | Category column |
| `database/factories/ExpenseFactory.php` | `expense_category_id` |
| `database/factories/ExpenseCategoryFactory.php` | `is_system` state |
| `lang/es/finance.php`, `lang/en/finance.php` | New validation/copy strings |
| `lang/es/settings.php`, `lang/en/settings.php` | Delete guard messages |
| `tests/Unit/Actions/SeedDefaultExpenseCategoriesActionTest.php` | Seed tests |
| `tests/Unit/Actions/RegisterExpenseActionTest.php` | Validation tests |
| `tests/Feature/Expenses/ExpenseCategoryFkTest.php` | End-to-end expense flows |
| `tests/Feature/Settings/OrganizationSettingsTest.php` | Category delete guards |
| `tests/Feature/Reports/CashFlowReportTest.php` | Category breakdown |
| `tests/Unit/Actions/ProcessContractSettlementActionTest.php` | Refund FK update |

---

### Task 1: Schema migration + data backfill

**Files:**
- Create: `database/migrations/2026_07_31_100000_add_expense_category_fk_and_contract_to_expenses.php`

**Interfaces:**
- Produces: columns `expense_categories.is_system`, `expenses.expense_category_id`, `expenses.contract_id`; drops `expenses.category`.

- [ ] **Step 1: Write migration**

```php
<?php

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Organization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_CATEGORIES = [
        ['name' => 'MANTENIMIENTO', 'is_system' => true],
        ['name' => 'LIMPIEZA', 'is_system' => true],
        ['name' => 'SERVICIO', 'is_system' => true],
        ['name' => 'REEMBOLSO DEPÓSITO', 'is_system' => true],
    ];

    public function up(): void
    {
        Schema::table('expense_categories', function (Blueprint $table): void {
            $table->boolean('is_system')->default(false)->after('is_active');
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->foreignId('expense_category_id')->nullable()->after('unit_id')->constrained('expense_categories');
            $table->foreignId('contract_id')->nullable()->after('expense_category_id')->constrained('contracts');
        });

        Organization::query()->withoutGlobalScopes()->orderBy('id')->each(function (Organization $organization): void {
            $categoryIdsByName = $this->seedCategoriesForOrganization((int) $organization->id);
            $this->backfillExpensesForOrganization((int) $organization->id, $categoryIdsByName);
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'category']);
            $table->dropColumn('category');
            $table->unsignedBigInteger('expense_category_id')->nullable(false)->change();
            $table->index(['organization_id', 'expense_category_id']);
            $table->index(['organization_id', 'contract_id']);
        });
    }

    /** @return array<string, int> */
    private function seedCategoriesForOrganization(int $organizationId): array
    {
        $map = [];

        foreach (self::DEFAULT_CATEGORIES as $row) {
            $category = ExpenseCategory::query()
                ->withoutOrganizationScope()
                ->firstOrCreate(
                    ['organization_id' => $organizationId, 'name' => $row['name']],
                    ['is_active' => true, 'is_system' => $row['is_system']],
                );

            if (! $category->is_system && $row['is_system']) {
                $category->forceFill(['is_system' => true])->save();
            }

            $map[strtoupper($category->name)] = (int) $category->id;
        }

        return $map;
    }

    /** @param array<string, int> $categoryIdsByName */
    private function backfillExpensesForOrganization(int $organizationId, array $categoryIdsByName): void
    {
        Expense::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->orderBy('id')
            ->each(function (Expense $expense) use ($organizationId, &$categoryIdsByName): void {
                $legacyCategory = strtoupper(trim((string) $expense->getRawOriginal('category')));

                if ($legacyCategory === 'REFUND DEPOSIT') {
                    $categoryId = $categoryIdsByName['REEMBOLSO DEPÓSITO'];
                } else {
                    $categoryId = $categoryIdsByName[$legacyCategory] ?? null;

                    if ($categoryId === null && $legacyCategory !== '') {
                        $created = ExpenseCategory::query()->withoutOrganizationScope()->firstOrCreate(
                            ['organization_id' => $organizationId, 'name' => $legacyCategory],
                            ['is_active' => true, 'is_system' => false],
                        );
                        $categoryId = (int) $created->id;
                        $categoryIdsByName[$legacyCategory] = $categoryId;
                    }

                    if ($categoryId === null) {
                        $categoryId = $categoryIdsByName['SERVICIO'];
                    }
                }

                $contractId = data_get($expense->meta, 'contract_id');
                $contractId = is_numeric($contractId) ? (int) $contractId : null;

                DB::table('expenses')->where('id', $expense->id)->update([
                    'expense_category_id' => $categoryId,
                    'contract_id' => $contractId,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->string('category')->nullable();
        });

        Expense::query()->withoutGlobalScopes()->with('expenseCategory')->each(function (Expense $expense): void {
            DB::table('expenses')->where('id', $expense->id)->update([
                'category' => $expense->expenseCategory?->name,
            ]);
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('contract_id');
            $table->dropConstrainedForeignId('expense_category_id');
            $table->index(['organization_id', 'category']);
        });

        Schema::table('expense_categories', function (Blueprint $table): void {
            $table->dropColumn('is_system');
        });
    }
};
```

- [ ] **Step 2: Run migration**

Run: `./vendor/bin/sail artisan migrate`
Expected: migration succeeds; existing expenses have `expense_category_id` set.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_07_31_100000_add_expense_category_fk_and_contract_to_expenses.php
git commit -m "feat(expenses): add category FK and contract_id with backfill"
```

---

### Task 2: Models + seed action

**Files:**
- Create: `app/Actions/Expenses/SeedDefaultExpenseCategoriesAction.php`
- Modify: `app/Models/ExpenseCategory.php`
- Modify: `app/Models/Expense.php`
- Test: `tests/Unit/Actions/SeedDefaultExpenseCategoriesActionTest.php`

**Interfaces:**
- Produces:
  - `SeedDefaultExpenseCategoriesAction::execute(int $organizationId): void`
  - `SeedDefaultExpenseCategoriesAction::depositRefundCategoryId(int $organizationId): int`
  - `ExpenseCategory::scopeActive()`, `scopeSystem()`, `expenses(): HasMany`
  - `Expense::expenseCategory()`, `contract()` BelongsTo

- [ ] **Step 1: Write failing unit test**

```php
<?php

namespace Tests\Unit\Actions;

use App\Actions\Expenses\SeedDefaultExpenseCategoriesAction;
use App\Models\ExpenseCategory;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedDefaultExpenseCategoriesActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_four_system_categories_idempotently(): void
    {
        $organization = Organization::factory()->create();
        $action = app(SeedDefaultExpenseCategoriesAction::class);

        $action->execute($organization->id);
        $action->execute($organization->id);

        $this->assertDatabaseCount('expense_categories', 4);
        $this->assertTrue(
            ExpenseCategory::query()->where('organization_id', $organization->id)->where('name', 'REEMBOLSO DEPÓSITO')->value('is_system')
        );
    }

    public function test_it_returns_deposit_refund_category_id(): void
    {
        $organization = Organization::factory()->create();
        $action = app(SeedDefaultExpenseCategoriesAction::class);
        $action->execute($organization->id);

        $id = $action->depositRefundCategoryId($organization->id);

        $this->assertSame(
            ExpenseCategory::query()->where('organization_id', $organization->id)->where('name', 'REEMBOLSO DEPÓSITO')->value('id'),
            $id
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail test --filter=SeedDefaultExpenseCategoriesActionTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement action + model updates**

`SeedDefaultExpenseCategoriesAction` — same four categories as migration; `depositRefundCategoryId()` throws `RuntimeException` if missing after seed.

Update `ExpenseCategory`:
- add `is_system` to `$fillable` and casts
- `expenses(): HasMany`
- `scopeActive($q)`, `scopeSystem($q)`

Update `Expense`:
- replace `category` with `expense_category_id`, `contract_id` in `$fillable`
- `expenseCategory(): BelongsTo`, `contract(): BelongsTo`
- auditable: `expense_category_id`, `contract_id` (remove `category`)

- [ ] **Step 4: Run tests**

Run: `./vendor/bin/sail test --filter=SeedDefaultExpenseCategoriesActionTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Actions/Expenses/SeedDefaultExpenseCategoriesAction.php app/Models/ExpenseCategory.php app/Models/Expense.php tests/Unit/Actions/SeedDefaultExpenseCategoriesActionTest.php
git commit -m "feat(expenses): add seed action and model relations for category FK"
```

---

### Task 3: `RegisterExpenseAction`

**Files:**
- Create: `app/Actions/Expenses/RegisterExpenseAction.php`
- Test: `tests/Unit/Actions/RegisterExpenseActionTest.php`

**Interfaces:**
- Produces: `RegisterExpenseAction::execute(int $organizationId, array $data): Expense`
- Input keys: `expense_category_id` (required), `amount`, `spent_at`, `unit_id` (nullable), `contract_id` (nullable), `vendor`, `notes`, `meta`

- [ ] **Step 1: Write failing tests**

Cover:
- creates expense with active category
- rejects category from another org
- rejects inactive category
- rejects contract whose `unit_id` differs from expense `unit_id`
- allows general expense (no unit, no contract)

- [ ] **Step 2: Run tests — expect FAIL**

Run: `./vendor/bin/sail test --filter=RegisterExpenseActionTest`

- [ ] **Step 3: Implement action**

Validation rules inside action:
- `expense_category_id` exists in `expense_categories` for org + `is_active`
- `unit_id` nullable, exists in `units` for org
- `contract_id` nullable, exists in `contracts` for org; if set, `unit_id` required and must match contract
- `amount` > 0, `spent_at` required date
- delegate create to `Expense::query()->create([...])` (MonthCloseGuard fires on model)

- [ ] **Step 4: Run tests — expect PASS**

- [ ] **Step 5: Commit**

---

### Task 4: Org registration seed hook

**Files:**
- Modify: `app/Http/Controllers/Auth/RegisterController.php`
- Test: extend `tests/Feature/Auth/AuthEventLogTest.php` or add assertion in existing registration test

- [ ] **Step 1: After org creation in `store()`, call `SeedDefaultExpenseCategoriesAction::execute($organization->id)`**

- [ ] **Step 2: Feature test — new org has 4 categories**

Run: `./vendor/bin/sail test --filter=Register`
Expected: PASS

- [ ] **Step 3: Commit**

---

### Task 5: Settlement + deposit service

**Files:**
- Modify: `app/Actions/Contracts/ProcessContractSettlementAction.php`
- Modify: `app/Support/DepositBalanceService.php`
- Modify: `app/Http/Controllers/ContractSettlementPdfController.php` (if still queries `category` string)
- Test: `tests/Unit/Actions/ProcessContractSettlementActionTest.php`

- [ ] **Step 1: Update settlement refund expense creation**

```php
$refundCategoryId = app(SeedDefaultExpenseCategoriesAction::class)
    ->depositRefundCategoryId($lockedContract->organization_id);

Expense::query()->withoutOrganizationScope()->create([
    'organization_id' => $lockedContract->organization_id,
    'unit_id' => $lockedContract->unit_id,
    'expense_category_id' => $refundCategoryId,
    'contract_id' => $lockedContract->id,
    // ...
]);
```

- [ ] **Step 2: Update `DepositBalanceService::refundedDepositAmount`**

```php
return round((float) Expense::query()
    ->withoutOrganizationScope()
    ->where('organization_id', $contract->organization_id)
    ->where('contract_id', $contract->id)
    ->whereHas('expenseCategory', fn ($q) => $q->where('is_system', true)->where('name', 'REEMBOLSO DEPÓSITO'))
    ->sum('amount'), 2);
```

- [ ] **Step 3: Run settlement tests**

Run: `./vendor/bin/sail test --filter=ProcessContractSettlementActionTest`
Expected: PASS (update assertions from `category` to relation)

- [ ] **Step 4: Commit**

---

### Task 6: Livewire capture UI

**Files:**
- Modify: `app/Livewire/Expenses/Index.php`
- Modify: `app/Livewire/Expenses/QuickRegisterModal.php`
- Modify: `resources/views/livewire/expenses/index.blade.php`
- Modify: `resources/views/livewire/expenses/quick-register-modal.blade.php`
- Test: `tests/Feature/Expenses/ExpenseCategoryFkTest.php`, update `QuickRegisterModalTest.php`

- [ ] **Step 1: Replace free-text category with `expense_category_id` select**

Load: `ExpenseCategory::query()->active()->orderBy('name')->get()`

- [ ] **Step 2: Unit select**

Reuse pattern from `Index` (already has units query with plaza filter). Quick modal: replace autocomplete with same select + optional "General".

- [ ] **Step 3: Optional contract select**

When `unit_id` set, load contracts for unit (`status` active first, then ended desc). Property `contract_id` nullable.

- [ ] **Step 4: `save()` delegates to `RegisterExpenseAction`**

Remove direct `Expense::create` and string `category` handling.

- [ ] **Step 5: Feature tests**

- cannot save without category
- saves with unit + contract
- contract/unit mismatch rejected

Run: `./vendor/bin/sail test --filter=ExpenseCategoryFkTest`
Run: `./vendor/bin/sail test --filter=QuickRegisterModalTest`

- [ ] **Step 6: Commit**

---

### Task 7: Settings delete guards

**Files:**
- Modify: `app/Livewire/Settings/Index.php`
- Modify: `resources/views/livewire/settings/index.blade.php` (optional badge for system categories)
- Modify: `lang/es/settings.php`, `lang/en/settings.php`
- Test: `tests/Feature/Settings/OrganizationSettingsTest.php`

- [ ] **Step 1: In `deleteExpenseCategory` / `executeDeleteConfirm`:**

```php
if ($category->is_system) {
    $this->addError('expenseCategory', __('settings.validation.category_system_delete_forbidden'));
    return;
}

if ($category->expenses()->exists()) {
    $this->addError('expenseCategory', __('settings.validation.category_in_use'));
    return;
}
```

- [ ] **Step 2: Tests for blocked delete**

Run: `./vendor/bin/sail test --filter=OrganizationSettingsTest`

- [ ] **Step 3: Commit**

---

### Task 8: Reports + factories + sweep

**Files:**
- Modify: `app/Livewire/Reports/CashFlow.php`
- Modify: `resources/views/livewire/reports/cash-flow.blade.php`
- Modify: `app/Http/Controllers/Reports/CashFlowCsvExportController.php`
- Modify: `database/factories/ExpenseFactory.php`
- Modify: `database/factories/ExpenseCategoryFactory.php`
- Modify: any test/factory still using `category` string (grep `'category'` on Expense)
- Test: `tests/Feature/Reports/CashFlowReportTest.php`

- [ ] **Step 1: Cash flow — eager load `expenseCategory`, compute `$expensesByCategory`**

```php
$expensesByCategory = $expenses
    ->groupBy(fn (Expense $e) => $e->expenseCategory?->name ?? '—')
    ->map(fn ($group) => round((float) $group->sum('amount'), 2))
    ->sortKeys();
```

- [ ] **Step 2: Update blade — small table or stat rows for category subtotals**

- [ ] **Step 3: Update `ExpenseFactory`**

```php
'expense_category_id' => ExpenseCategory::factory()->state(fn (array $attributes) => [
    'organization_id' => $attributes['organization_id'],
]),
// remove 'category'
```

- [ ] **Step 4: Grep sweep**

Run: `rg "'category'" app tests database --glob '*.php' | rg -i expense`
Fix remaining references.

- [ ] **Step 5: Run full expense/report test suite**

Run: `./vendor/bin/sail test --filter=Expense`
Run: `./vendor/bin/sail test --filter=CashFlowReportTest`
Run: `./vendor/bin/sail pint --dirty`

- [ ] **Step 6: Commit**

---

## Spec Self-Review

| Spec requirement | Task |
|------------------|------|
| FK obligatoria categoría | Task 1, 2, 3, 6 |
| Defaults 4 categorías | Task 1, 2, 4 |
| `is_system` + delete guards | Task 1, 2, 7 |
| `contract_id` opcional | Task 1, 3, 6 |
| Sin `tenant_id` | Design only — not added |
| Finiquito REEMBOLSO DEPÓSITO | Task 5 |
| Select unidad/categoría | Task 6 |
| Reportes por categoría | Task 8 |
| Migración strings históricos | Task 1 |
| MonthCloseGuard unchanged | No task needed |
| Permisos sin cambios | No task needed |

No TBD placeholders in plan. Type names consistent across tasks.

---

**Plan complete and saved to `docs/superpowers/plans/2026-07-31-expense-categories-fk.md`.**

**Two execution options:**

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks  
2. **Inline Execution** — implement task-by-task in this session with checkpoints

Which approach do you want?
