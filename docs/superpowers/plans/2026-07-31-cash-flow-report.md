# Cash Flow Report Congruence Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `/reports/flow` and its CSV export share one calculation path so totals stay congruent with operating allocations, plaza-scoped expenses (including general), and month-close snapshots.

**Architecture:** Introduce `CashFlowReportService` as the single builder for income/expense/net + snapshot flags. Fix `OperatingIncomeService` join asymmetry (LEFT JOIN everywhere). Thin Livewire and CSV controller consume the service only. Plaza comes from `TenantContext`; snapshot banner only when full calendar month and no plaza filter.

**Tech Stack:** Laravel 11, Livewire 4, PHPUnit via Sail, CarbonImmutable, Tailwind Blade.

## Global Constraints

- Run all commands via `./vendor/bin/sail` (never bare `php artisan` / `phpunit`).
- Multi-tenant: filter by `organization_id`; use `withoutOrganizationScope()` only where existing report queries already do.
- Permissions unchanged: `reports.view`, `reports.export`.
- Timezone for report bounds: `America/Tijuana`.
- UI dates display `d/m/Y` via `DateDisplay` / `x-ui.display-date`.
- Business logic in `app/Support`; Livewire validates dates and delegates.
- Spec: `docs/superpowers/specs/2026-07-31-cash-flow-report-design.md`.
- Verify: `./vendor/bin/sail test --filter=CashFlow` + `./vendor/bin/sail pint --dirty`.

---

## File Map

| File | Responsibility |
|------|----------------|
| `app/Support/OperatingIncomeService.php` | Unify LEFT JOIN in `totalsByTypeForRange` |
| `app/Support/CashFlowReportService.php` | Single `build()` for report payload |
| `app/Livewire/Reports/CashFlow.php` | Date validation + call service + pass view data |
| `app/Http/Controllers/Reports/CashFlowCsvExportController.php` | Same service + timezone; stream CSV |
| `resources/views/livewire/reports/cash-flow.blade.php` | Keep current UI; bind service fields only |
| `tests/Unit/Support/OperatingIncomeServiceTest.php` | Detail totals === type totals |
| `tests/Unit/Support/CashFlowReportServiceTest.php` | Plaza expenses + snapshot rules |
| `tests/Feature/Reports/CashFlowReportTest.php` | UI/CSV parity + plaza + snapshot banner |

---

### Task 1: Unify OperatingIncomeService joins

**Files:**
- Modify: `app/Support/OperatingIncomeService.php` (`totalsByTypeForRange`)
- Create: `tests/Unit/Support/OperatingIncomeServiceTest.php`

**Interfaces:**
- Consumes: existing `allocationsForRange` / `totalsByTypeForRange` signatures (unchanged).
- Produces: `totalsByTypeForRange` uses the same LEFT JOIN path as `allocationsForRange` so sums match.

- [ ] **Step 1: Write the failing unit test**

Create `tests/Unit/Support/OperatingIncomeServiceTest.php`:

```php
<?php

namespace Tests\Unit\Support;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\OperatingIncomeService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatingIncomeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_totals_by_type_match_sum_of_allocation_details(): void
    {
        $organization = Organization::factory()->create();
        User::factory()->create(['organization_id' => $organization->id]);

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
        ]);

        $rent = Charge::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_RENT,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 1000,
            'meta' => [],
        ]);
        $penalty = Charge::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_PENALTY,
            'period' => '2026-03',
            'charge_date' => '2026-03-07',
            'amount' => 120,
            'meta' => [],
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'paid_at' => '2026-03-10 10:00:00',
            'amount' => 1120,
            'method' => Payment::METHOD_TRANSFER,
            'reference' => 'P-JOIN',
            'receipt_folio' => 'REC-JOIN-001',
            'meta' => [],
        ]);

        PaymentAllocation::query()->create([
            'organization_id' => $organization->id,
            'payment_id' => $payment->id,
            'charge_id' => $rent->id,
            'amount' => 1000,
            'meta' => [],
        ]);
        PaymentAllocation::query()->create([
            'organization_id' => $organization->id,
            'payment_id' => $payment->id,
            'charge_id' => $penalty->id,
            'amount' => 120,
            'meta' => [],
        ]);

        $from = CarbonImmutable::parse('2026-03-01', 'America/Tijuana')->startOfDay();
        $to = CarbonImmutable::parse('2026-03-31', 'America/Tijuana')->endOfDay();
        $service = app(OperatingIncomeService::class);

        $detailsSum = round((float) $service->allocationsForRange(
            (int) $organization->id,
            $from,
            $to,
        )->sum('allocated_amount'), 2);
        $typesSum = round((float) array_sum($service->totalsByTypeForRange(
            (int) $organization->id,
            $from,
            $to,
        )), 2);

        $this->assertSame(1120.0, $detailsSum);
        $this->assertSame($detailsSum, $typesSum);
    }
}
```

- [ ] **Step 2: Run test to verify it fails or passes baseline**

Run: `./vendor/bin/sail test --filter=OperatingIncomeServiceTest`

Expected: PASS with current data (both paths join units). The regression guard is still required before changing joins. If it fails, fix data setup first.

- [ ] **Step 3: Change `totalsByTypeForRange` to LEFT JOIN**

In `app/Support/OperatingIncomeService.php`, inside `totalsByTypeForRange`, replace:

```php
->join('contracts', 'contracts.id', '=', 'payments.contract_id')
->join('units', 'units.id', '=', 'contracts.unit_id')
->join('properties', 'properties.id', '=', 'units.property_id')
```

with:

```php
->join('contracts', 'contracts.id', '=', 'payments.contract_id')
->leftJoin('units', 'units.id', '=', 'contracts.unit_id')
->leftJoin('properties', 'properties.id', '=', 'units.property_id')
```

Keep the plaza `when($plazaId !== null, ...)` clause unchanged (`where('properties.plaza_id', $plazaId)`).

- [ ] **Step 4: Re-run unit test**

Run: `./vendor/bin/sail test --filter=OperatingIncomeServiceTest`

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Support/OperatingIncomeService.php tests/Unit/Support/OperatingIncomeServiceTest.php
git commit -m "$(cat <<'EOF'
Align operating income totals joins with allocation details.

EOF
)"
```

---

### Task 2: CashFlowReportService — build + plaza expenses + snapshot rules

**Files:**
- Create: `app/Support/CashFlowReportService.php`
- Create: `tests/Unit/Support/CashFlowReportServiceTest.php`

**Interfaces:**
- Consumes: `OperatingIncomeService::allocationsForRange`, `totalsByTypeForRange`, `operatingChargeTypes`; `Expense`, `MonthClose`.
- Produces:

```php
/**
 * @return array{
 *   incomeTotal: float,
 *   expenseTotal: float,
 *   netTotal: float,
 *   incomeCount: int,
 *   expenseCount: int,
 *   incomeByType: array<string, float>,
 *   incomeDetails: \Illuminate\Support\Collection,
 *   expenses: \Illuminate\Support\Collection,
 *   expensesByCategory: \Illuminate\Support\Collection,
 *   operatingChargeTypes: list<string>,
 *   closedMonthSnapshot: ?array<string, mixed>,
 *   snapshotMatches: ?bool
 * }
 */
public function build(
    int $organizationId,
    CarbonImmutable $dateFrom,
    CarbonImmutable $dateTo,
    ?int $plazaId = null
): array
```

- [ ] **Step 1: Write failing tests**

Create `tests/Unit/Support/CashFlowReportServiceTest.php`:

```php
<?php

namespace Tests\Unit\Support;

use App\Actions\Expenses\SeedDefaultExpenseCategoriesAction;
use App\Actions\MonthCloses\CloseMonthAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\MonthClose;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Plaza;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\CashFlowReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashFlowReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_plaza_scope_includes_general_expenses_and_excludes_other_plaza(): void
    {
        [$organization, $plazaA, $plazaB, $unitA] = $this->seedOrgWithTwoPlazas();

        app(SeedDefaultExpenseCategoriesAction::class)->execute((int) $organization->id);
        $categoryId = (int) ExpenseCategory::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organization->id)
            ->where('name', 'MANTENIMIENTO')
            ->value('id');

        Expense::query()->create([
            'organization_id' => $organization->id,
            'unit_id' => null,
            'expense_category_id' => $categoryId,
            'amount' => 50,
            'spent_at' => '2026-03-10',
            'vendor' => 'General',
            'notes' => null,
            'meta' => [],
        ]);
        Expense::query()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unitA->id,
            'expense_category_id' => $categoryId,
            'amount' => 80,
            'spent_at' => '2026-03-11',
            'vendor' => 'Plaza A',
            'notes' => null,
            'meta' => [],
        ]);

        $unitB = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => Property::factory()->create([
                'organization_id' => $organization->id,
                'plaza_id' => $plazaB->id,
                'name' => 'PROPERTY PLAZA B',
            ])->id,
        ]);
        Expense::query()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unitB->id,
            'expense_category_id' => $categoryId,
            'amount' => 999,
            'spent_at' => '2026-03-12',
            'vendor' => 'Plaza B',
            'notes' => null,
            'meta' => [],
        ]);

        $from = CarbonImmutable::parse('2026-03-01', 'America/Tijuana')->startOfDay();
        $to = CarbonImmutable::parse('2026-03-31', 'America/Tijuana')->endOfDay();

        $report = app(CashFlowReportService::class)->build(
            (int) $organization->id,
            $from,
            $to,
            (int) $plazaA->id,
        );

        $this->assertSame(130.0, $report['expenseTotal']);
        $this->assertSame(2, $report['expenseCount']);
        $this->assertTrue($report['expenses']->contains(fn ($e) => $e->vendor === 'General'));
        $this->assertFalse($report['expenses']->contains(fn ($e) => $e->vendor === 'Plaza B'));
    }

    public function test_snapshot_only_when_full_month_and_no_plaza(): void
    {
        [$user, $organization] = $this->seedIncomeAndExpenseForMarch();

        app(CloseMonthAction::class)->execute(
            organizationId: (int) $organization->id,
            userId: (int) $user->id,
            month: '2026-03',
            notes: 'parity',
        );

        $from = CarbonImmutable::parse('2026-03-01', 'America/Tijuana')->startOfDay();
        $to = CarbonImmutable::parse('2026-03-31', 'America/Tijuana')->endOfDay();
        $service = app(CashFlowReportService::class);

        $orgWide = $service->build((int) $organization->id, $from, $to, null);
        $this->assertIsArray($orgWide['closedMonthSnapshot']);
        $this->assertTrue($orgWide['snapshotMatches']);

        $plaza = Plaza::factory()->create(['organization_id' => $organization->id]);
        $withPlaza = $service->build((int) $organization->id, $from, $to, (int) $plaza->id);
        $this->assertNull($withPlaza['closedMonthSnapshot']);
        $this->assertNull($withPlaza['snapshotMatches']);

        $partial = $service->build(
            (int) $organization->id,
            $from,
            CarbonImmutable::parse('2026-03-15', 'America/Tijuana')->endOfDay(),
            null,
        );
        $this->assertNull($partial['closedMonthSnapshot']);
    }

    /**
     * @return array{0: Organization, 1: Plaza, 2: Plaza, 3: Unit}
     */
    private function seedOrgWithTwoPlazas(): array
    {
        $organization = Organization::factory()->create();
        $plazaA = Plaza::factory()->create([
            'organization_id' => $organization->id,
            'nombre' => 'Plaza A',
            'is_default' => true,
        ]);
        $plazaB = Plaza::factory()->create([
            'organization_id' => $organization->id,
            'nombre' => 'Plaza B',
            'is_default' => false,
        ]);
        $propertyA = Property::factory()->create([
            'organization_id' => $organization->id,
            'plaza_id' => $plazaA->id,
            'name' => 'PROPERTY PLAZA A',
        ]);
        $unitA = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $propertyA->id,
        ]);

        return [$organization, $plazaA, $plazaB, $unitA];
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function seedIncomeAndExpenseForMarch(): array
    {
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
        ]);

        $rent = Charge::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_RENT,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 1000,
            'meta' => [],
        ]);
        $payment = Payment::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'paid_at' => '2026-03-05 10:00:00',
            'amount' => 1000,
            'method' => Payment::METHOD_TRANSFER,
            'reference' => 'P-1',
            'receipt_folio' => 'REC-CF-1',
            'meta' => [],
        ]);
        PaymentAllocation::query()->create([
            'organization_id' => $organization->id,
            'payment_id' => $payment->id,
            'charge_id' => $rent->id,
            'amount' => 1000,
            'meta' => [],
        ]);

        app(SeedDefaultExpenseCategoriesAction::class)->execute((int) $organization->id);
        $categoryId = ExpenseCategory::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organization->id)
            ->where('name', 'MANTENIMIENTO')
            ->value('id');

        Expense::query()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'expense_category_id' => $categoryId,
            'amount' => 300,
            'spent_at' => '2026-03-12',
            'vendor' => 'Proveedor',
            'notes' => null,
            'meta' => [],
        ]);

        return [$user, $organization];
    }
}
```

Prerequisite: `SeedDefaultExpenseCategoriesAction` and expense category FK must exist in the working tree (same branch as expense-categories work). If `CashFlowReportTest` already seeds categories another way, mirror that helper instead of inventing a second path.

- [ ] **Step 2: Run tests — expect FAIL**

Run: `./vendor/bin/sail test --filter=CashFlowReportServiceTest`

Expected: FAIL with class `CashFlowReportService` not found.

- [ ] **Step 3: Implement `CashFlowReportService`**

Create `app/Support/CashFlowReportService.php`:

```php
<?php

namespace App\Support;

use App\Models\Expense;
use App\Models\MonthClose;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CashFlowReportService
{
    public function __construct(
        private readonly OperatingIncomeService $operatingIncomeService,
    ) {}

    /**
     * @return array{
     *     incomeTotal: float,
     *     expenseTotal: float,
     *     netTotal: float,
     *     incomeCount: int,
     *     expenseCount: int,
     *     incomeByType: array<string, float>,
     *     incomeDetails: Collection<int, array<string, mixed>>,
     *     expenses: Collection<int, Expense>,
     *     expensesByCategory: Collection<string, float>,
     *     operatingChargeTypes: list<string>,
     *     closedMonthSnapshot: ?array<string, mixed>,
     *     snapshotMatches: ?bool
     * }
     */
    public function build(
        int $organizationId,
        CarbonImmutable $dateFrom,
        CarbonImmutable $dateTo,
        ?int $plazaId = null
    ): array {
        $incomeDetails = $this->operatingIncomeService->allocationsForRange(
            $organizationId,
            $dateFrom,
            $dateTo,
            $plazaId,
        );
        $incomeByType = $this->operatingIncomeService->totalsByTypeForRange(
            $organizationId,
            $dateFrom,
            $dateTo,
            $plazaId,
        );
        $incomeTotal = round((float) array_sum($incomeByType), 2);

        $expenses = Expense::query()
            ->with(['unit.property', 'expenseCategory'])
            ->where('organization_id', $organizationId)
            ->when($plazaId !== null, function (Builder $query) use ($plazaId): void {
                $query->where(function (Builder $scoped) use ($plazaId): void {
                    $scoped->whereNull('unit_id')
                        ->orWhereHas('unit.property', function (Builder $propertyQuery) use ($plazaId): void {
                            $propertyQuery->where('plaza_id', $plazaId);
                        });
                });
            })
            ->whereBetween('spent_at', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->orderBy('spent_at')
            ->get();

        $expenseTotal = round((float) $expenses->sum('amount'), 2);
        $expensesByCategory = $expenses
            ->groupBy(fn (Expense $expense) => $expense->expenseCategory?->name ?? '—')
            ->map(fn ($group) => round((float) $group->sum('amount'), 2))
            ->sortKeys();

        $netTotal = round($incomeTotal - $expenseTotal, 2);

        $closedMonthSnapshot = $this->resolveClosedMonthSnapshot(
            $organizationId,
            $dateFrom,
            $dateTo,
            $plazaId,
        );
        $snapshotMatches = null;
        if ($closedMonthSnapshot !== null) {
            $snapshotMatches = round((float) ($closedMonthSnapshot['ingresos_operativos'] ?? 0), 2) === $incomeTotal
                && round((float) ($closedMonthSnapshot['egresos'] ?? 0), 2) === $expenseTotal
                && round((float) ($closedMonthSnapshot['neto'] ?? 0), 2) === $netTotal;
        }

        return [
            'incomeTotal' => $incomeTotal,
            'expenseTotal' => $expenseTotal,
            'netTotal' => $netTotal,
            'incomeCount' => $incomeDetails->count(),
            'expenseCount' => $expenses->count(),
            'incomeByType' => $incomeByType,
            'incomeDetails' => $incomeDetails,
            'expenses' => $expenses,
            'expensesByCategory' => $expensesByCategory,
            'operatingChargeTypes' => $this->operatingIncomeService->operatingChargeTypes(),
            'closedMonthSnapshot' => $closedMonthSnapshot,
            'snapshotMatches' => $snapshotMatches,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveClosedMonthSnapshot(
        int $organizationId,
        CarbonImmutable $dateFrom,
        CarbonImmutable $dateTo,
        ?int $plazaId
    ): ?array {
        if ($plazaId !== null) {
            return null;
        }

        $monthStart = $dateFrom->startOfMonth()->toDateString();
        $monthEnd = $dateFrom->endOfMonth()->toDateString();

        if (
            $dateFrom->toDateString() !== $monthStart
            || $dateTo->toDateString() !== $monthEnd
            || $dateFrom->format('Y-m') !== $dateTo->format('Y-m')
        ) {
            return null;
        }

        $monthClose = MonthClose::query()
            ->where('organization_id', $organizationId)
            ->where('month', $dateFrom->format('Y-m'))
            ->first();

        if ($monthClose === null) {
            return null;
        }

        return is_array($monthClose->snapshot) ? $monthClose->snapshot : null;
    }
}
```

If `Expense` is organization-scoped and auto-filters `organization_id`, the explicit `where('organization_id', ...)` is still fine for clarity in `withoutOrganizationScope` contexts; keep consistent with surrounding code (drop redundant where if scope already applies in tests).

- [ ] **Step 4: Run tests — expect PASS**

Run: `./vendor/bin/sail test --filter=CashFlowReportServiceTest`

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Support/CashFlowReportService.php tests/Unit/Support/CashFlowReportServiceTest.php
git commit -m "$(cat <<'EOF'
Add CashFlowReportService as single cash flow calculation path.

EOF
)"
```

---

### Task 3: Wire Livewire + CSV to the service

**Files:**
- Modify: `app/Livewire/Reports/CashFlow.php`
- Modify: `app/Http/Controllers/Reports/CashFlowCsvExportController.php`
- Modify: `tests/Feature/Reports/CashFlowReportTest.php`

**Interfaces:**
- Consumes: `CashFlowReportService::build(...)`
- Produces: UI and CSV use identical totals for the same org/range/plaza.

- [ ] **Step 1: Add failing feature tests for parity and plaza snapshot**

Append to `tests/Feature/Reports/CashFlowReportTest.php`:

```php
public function test_ui_and_csv_share_the_same_totals(): void
{
    [$user] = $this->seedFinanceData();

    $this->actingAs($user);

    Livewire::test(\App\Livewire\Reports\CashFlow::class)
        ->set('date_from', '2026-03-01')
        ->set('date_to', '2026-03-31')
        ->assertSee('$1,500.00')
        ->assertSee('$300.00')
        ->assertSee('$1,200.00');

    $csv = $this->actingAs($user)->get(route('reports.flow.export.csv', [
        'date_from' => '2026-03-01',
        'date_to' => '2026-03-31',
    ]))->streamedContent();

    $this->assertStringContainsString('TOTAL_INGRESOS,1500.00', $csv);
    $this->assertStringContainsString('TOTAL_EGRESOS,300.00', $csv);
    $this->assertStringContainsString('NETO,1200.00', $csv);
}

public function test_snapshot_banner_hidden_when_plaza_is_active(): void
{
    [$user] = $this->seedFinanceData();

    app(CloseMonthAction::class)->execute(
        organizationId: (int) $user->organization_id,
        userId: (int) $user->id,
        month: '2026-03',
        notes: 'Close for plaza banner',
    );

    $plaza = Plaza::factory()->create([
        'organization_id' => $user->organization_id,
    ]);
    $sessionKey = TenantContext::sessionKeyForCurrentPlaza((int) $user->id);

    $this->actingAs($user)
        ->withSession([$sessionKey => $plaza->id])
        ->get(route('reports.flow', [
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
        ]))
        ->assertOk()
        ->assertDontSee('Mes cerrado: el reporte coincide con el snapshot.')
        ->assertDontSee('Mes cerrado: hay diferencia contra snapshot de cierre.');
}
```

Add imports at top of the test file if missing:

```php
use App\Models\Plaza;
use App\Support\TenantContext;
```

- [ ] **Step 2: Run new tests — may FAIL on plaza banner if Livewire still shows snapshot**

Run: `./vendor/bin/sail test --filter=CashFlowReportTest`

Expected: plaza banner test FAIL (or PASS only after Task 2 wiring). Proceed to wire consumers.

- [ ] **Step 3: Rewrite `CashFlow` Livewire `render()` to use the service**

Replace calculation body in `app/Livewire/Reports/CashFlow.php` with:

```php
public function render(): View
{
    $this->validate($this->rules(), $this->messages());

    $dateFrom = CarbonImmutable::parse($this->date_from, 'America/Tijuana')->startOfDay();
    $dateTo = CarbonImmutable::parse($this->date_to, 'America/Tijuana')->endOfDay();
    $organizationId = (int) auth()->user()?->organization_id;
    $currentPlazaId = TenantContext::currentPlazaId();

    $report = app(CashFlowReportService::class)->build(
        $organizationId,
        $dateFrom,
        $dateTo,
        $currentPlazaId,
    );

    $exportUrl = route('reports.flow.export.csv', [
        'date_from' => $this->date_from,
        'date_to' => $this->date_to,
    ]);

    return view('livewire.reports.cash-flow', [
        ...$report,
        'exportUrl' => $exportUrl,
    ])->layout('layouts.app', ['title' => __('finance.cash_flow.title')]);
}
```

Remove private `resolveClosedMonthSnapshot` from the Livewire class. Update imports: add `CashFlowReportService`; drop unused `Expense`, `MonthClose`, `Builder` if no longer referenced.

Keep `mount`, `rules`, `messages`, and `$queryString` as they are.

- [ ] **Step 4: Rewrite CSV controller to use the service + Tijuana timezone**

Replace `__invoke` body in `app/Http/Controllers/Reports/CashFlowCsvExportController.php`:

```php
public function __invoke(Request $request, CashFlowReportService $cashFlowReportService): StreamedResponse
{
    $validated = $request->validate([
        'date_from' => ['required', 'date'],
        'date_to' => ['required', 'date', 'after_or_equal:date_from'],
    ]);

    $dateFrom = CarbonImmutable::parse($validated['date_from'], 'America/Tijuana')->startOfDay();
    $dateTo = CarbonImmutable::parse($validated['date_to'], 'America/Tijuana')->endOfDay();
    $organizationId = (int) $request->user()?->organization_id;
    $currentPlazaId = TenantContext::currentPlazaId();

    $report = $cashFlowReportService->build(
        $organizationId,
        $dateFrom,
        $dateTo,
        $currentPlazaId,
    );

    $filename = 'cash-flow-'.$dateFrom->format('Ymd').'-'.$dateTo->format('Ymd').'.csv';

    return response()->streamDownload(function () use ($report): void {
        $output = fopen('php://output', 'w');

        if (! is_resource($output)) {
            return;
        }

        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, ['SECCION', 'INGRESOS_ALLOCATIONS']);
        fputcsv($output, ['fecha_pago', 'folio', 'contract_id', 'inquilino', 'propiedad', 'unidad', 'tipo', 'monto']);
        foreach ($report['incomeDetails'] as $row) {
            fputcsv($output, [
                DateDisplay::formatDateTime($row['paid_at']),
                $row['receipt_folio'] ?? '',
                $row['contract_id'],
                $row['tenant_name'] ?? '',
                $row['property_name'] ?? '',
                $row['unit_name'] ?? ($row['unit_code'] ?? ''),
                $row['charge_type'],
                number_format((float) $row['allocated_amount'], 2, '.', ''),
            ]);
        }

        fputcsv($output, []);
        fputcsv($output, ['SECCION', 'INGRESOS_POR_TIPO']);
        fputcsv($output, ['tipo', 'total']);
        foreach ($report['incomeByType'] as $type => $total) {
            fputcsv($output, [(string) $type, number_format((float) $total, 2, '.', '')]);
        }

        fputcsv($output, []);
        fputcsv($output, ['SECCION', 'EGRESOS']);
        fputcsv($output, ['fecha', 'categoria', 'propiedad', 'unidad', 'proveedor', 'monto']);
        foreach ($report['expenses'] as $expense) {
            fputcsv($output, [
                DateDisplay::formatDate($expense->spent_at),
                $expense->expenseCategory?->name ?? '',
                $expense->unit?->property?->name ?? '',
                $expense->unit?->name ?? '',
                $expense->vendor ?: 'Sin proveedor',
                number_format((float) $expense->amount, 2, '.', ''),
            ]);
        }

        fputcsv($output, []);
        fputcsv($output, ['RESUMEN', '', '', '', 'TOTAL_INGRESOS', number_format($report['incomeTotal'], 2, '.', '')]);
        fputcsv($output, ['RESUMEN', '', '', '', 'TOTAL_EGRESOS', number_format($report['expenseTotal'], 2, '.', '')]);
        fputcsv($output, ['RESUMEN', '', '', '', 'NETO', number_format($report['netTotal'], 2, '.', '')]);

        fclose($output);
    }, $filename, [
        'Content-Type' => 'text/csv; charset=UTF-8',
    ]);
}
```

Update imports: `CashFlowReportService`; remove unused `Expense`, `OperatingIncomeService`, `Builder` if unused.

- [ ] **Step 5: Confirm Blade still receives expected keys**

`resources/views/livewire/reports/cash-flow.blade.php` already expects: `incomeTotal`, `expenseTotal`, `netTotal`, `incomeCount`, `expenseCount`, `incomeByType`, `incomeDetails`, `expenses`, `expensesByCategory`, `operatingChargeTypes`, `closedMonthSnapshot`, `snapshotMatches`, `exportUrl`. No structural redesign required. Only touch Blade if a key name mismatch appears during tests.

- [ ] **Step 6: Run feature + unit suite for CashFlow**

Run: `./vendor/bin/sail test --filter=CashFlow`

Expected: PASS (including existing deposit exclusion, RENT/PENALTY split, month-close match without plaza).

- [ ] **Step 7: Pint + commit**

```bash
./vendor/bin/sail pint --dirty
git add app/Livewire/Reports/CashFlow.php \
  app/Http/Controllers/Reports/CashFlowCsvExportController.php \
  resources/views/livewire/reports/cash-flow.blade.php \
  tests/Feature/Reports/CashFlowReportTest.php
git commit -m "$(cat <<'EOF'
Route cash flow UI and CSV through CashFlowReportService.

EOF
)"
```

---

### Task 4: Final verification

**Files:** none new (verification only)

- [ ] **Step 1: Run focused tests**

```bash
./vendor/bin/sail test --filter=CashFlow
./vendor/bin/sail test --filter=OperatingIncomeServiceTest
./vendor/bin/sail test --filter=PlazaScopedScreensTest
```

Expected: all PASS.

- [ ] **Step 2: Format**

```bash
./vendor/bin/sail pint --dirty
```

- [ ] **Step 3: Optional smoke (financial path)**

```bash
./vendor/bin/sail artisan inmo:smoke --date=2026-03-10
```

Expected: exit 0 (skip if environment not seeded for smoke).

- [ ] **Step 4: Commit any pint-only fixes if present**

```bash
git status
# if dirty formatting:
git add -u
git commit -m "$(cat <<'EOF'
Apply pint formatting after cash flow report changes.

EOF
)"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Single calculation path UI + CSV | Task 2 + 3 |
| LEFT JOIN parity in OperatingIncomeService | Task 1 |
| Plaza includes `unit_id` null expenses | Task 2 |
| Snapshot only full month + no plaza | Task 2 + 3 |
| Timezone America/Tijuana in CSV | Task 3 |
| No new filters / no month-close rewrite | Out of scope; not tasked |
| Tests: UI/CSV parity, plaza, snapshot, join sum | Tasks 1–3 |
