# Settlement Refresh on Deposit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refresh the Finiquito deposit summary (registrado / aplicado / devuelto / disponible / adeudo) when a deposit hold is registered or voided on the same contract page, without a full reload.

**Architecture:** Add empty Livewire `#[On]` listeners on `SettlementWizard` for `deposit-hold-registered` and `deposit-hold-voided` (same pattern as `Contracts\Show`). That forces a re-render so `DepositBalanceService` totals in `render()` update. No Action or Blade changes.

**Tech Stack:** Laravel 11, Livewire 4, PHPUnit via Sail, Pint.

## Global Constraints

- Run all commands via `./vendor/bin/sail` (never bare `php artisan` / `phpunit` / `pint`).
- Diff mínimo: only `SettlementWizard.php` + a feature test. Do not touch Actions, `DepositBalanceService`, or settlement Blade markup.
- Spec: `docs/superpowers/specs/2026-07-21-settlement-refresh-on-deposit-design.md`.
- UI lines that must refresh: `Depósito registrado`, `Depósito aplicado`, `Depósito devuelto`, `Disponible`, `Adeudo actual` (keys `deposit_paid`, `deposit_applied`, `deposit_refunded`, `available`, `current_outstanding`).
- Tests: `./vendor/bin/sail test --filter=SettlementWizard`; format: `./vendor/bin/sail pint --dirty`.
- No commit unless the user explicitly asks (repo user rule).

---

## File Map

| File | Responsibility |
|------|----------------|
| `app/Livewire/Contracts/SettlementWizard.php` | Listen to deposit-hold events → re-render |
| `tests/Feature/Contracts/SettlementWizardDepositRefreshTest.php` | Assert summary updates after register/void events |
| `docs/superpowers/specs/2026-07-21-settlement-refresh-on-deposit-design.md` | Already Approved (no change required) |

---

### Task 1: Failing feature tests for deposit refresh

**Files:**
- Create: `tests/Feature/Contracts/SettlementWizardDepositRefreshTest.php`

**Interfaces:**
- Consumes: `SettlementWizard`, `DepositBalanceService` behavior via rendered HTML, events `deposit-hold-registered` / `deposit-hold-voided`
- Produces: RED tests that fail until listeners exist (or until re-render path works)

- [ ] **Step 1: Write the failing test file**

Create `tests/Feature/Contracts/SettlementWizardDepositRefreshTest.php`:

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

class SettlementWizardDepositRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_updates_when_deposit_hold_registered_event_fires(): void
    {
        [$user, $contract] = $this->makeContractWithUser(depositAmount: 10000.0);

        $component = Livewire::actingAs($user)
            ->test(SettlementWizard::class, ['contract' => $contract])
            ->assertSee(__('contracts.deposit_paid'))
            ->assertSee('$0.00');

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-07',
            'charge_date' => '2026-07-21',
            'amount' => 10000,
        ]);

        // Without listeners, dispatch alone may not refresh; after Task 2 it must.
        $component
            ->dispatch('deposit-hold-registered')
            ->assertSee(__('contracts.deposit_paid'))
            ->assertSee('$10,000.00')
            ->assertSee(__('contracts.available'))
            ->assertSee(__('contracts.deposit_applied'))
            ->assertSee(__('contracts.deposit_refunded'))
            ->assertSee(__('contracts.current_outstanding'));
    }

    public function test_summary_updates_when_deposit_hold_voided_event_fires(): void
    {
        [$user, $contract] = $this->makeContractWithUser(depositAmount: 10000.0);

        $hold = Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-07',
            'charge_date' => '2026-07-21',
            'amount' => 10000,
        ]);

        $component = Livewire::actingAs($user)
            ->test(SettlementWizard::class, ['contract' => $contract])
            ->assertSee('$10,000.00');

        $hold->delete(); // soft-delete; registered sum excludes it

        $component
            ->dispatch('deposit-hold-voided')
            ->assertSee(__('contracts.deposit_paid'))
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

Notes:
- If `Charge` soft-deletes and `registeredDepositHoldAmount` already ignores soft-deleted rows, void test is valid. If soft-delete is not used, use `forceDelete()` or match `VoidDepositHoldAction` behavior — inspect `Charge` model / void action if the void test fails for the wrong reason.
- Multiple `$0.00` on the page is OK for `assertSee`; the critical assertion after register is `$10,000.00` appearing after dispatch.
- If RED does not fail before listeners exist (Livewire always re-renders on `dispatch`), still add the listeners in Task 2 for production sibling-component events, and keep tests as regression coverage. Document that in the report.

- [ ] **Step 2: Run tests expecting RED (or note if dispatch already re-renders in isolation)**

```bash
./vendor/bin/sail test --filter=SettlementWizardDepositRefresh
```

Expected: preferably FAIL before Task 2. If both PASS without listeners (test isolation re-renders on every dispatch), proceed to Task 2 anyway — listeners are still required for browser sibling components.

- [ ] **Step 3: Stop — implementation is Task 2**

---

### Task 2: Add SettlementWizard event listeners

**Files:**
- Modify: `app/Livewire/Contracts/SettlementWizard.php`

**Interfaces:**
- Consumes: Livewire events `deposit-hold-registered`, `deposit-hold-voided` (already dispatched by `DepositHoldForm`)
- Produces: `onDepositHoldChanged(): void` empty handler that triggers re-render

- [ ] **Step 1: Add imports and listener**

Near the top of `app/Livewire/Contracts/SettlementWizard.php`, ensure:

```php
use Livewire\Attributes\On;
```

Inside the class (after properties / before `mount` is fine), add:

```php
#[On('deposit-hold-registered')]
#[On('deposit-hold-voided')]
public function onDepositHoldChanged(): void {}
```

Mirror `app/Livewire/Contracts/Show.php` (`onDepositHoldChanged`). Do not reset `concepts`, `move_out_date`, or last settlement PDF fields.

- [ ] **Step 2: Run tests GREEN**

```bash
./vendor/bin/sail test --filter=SettlementWizardDepositRefresh
```

Expected: PASS.

- [ ] **Step 3: Format**

```bash
./vendor/bin/sail pint --dirty
```

- [ ] **Step 4: Commit only if the user asks**

Do not commit unless explicitly requested. If requested:

```bash
git add \
  app/Livewire/Contracts/SettlementWizard.php \
  tests/Feature/Contracts/SettlementWizardDepositRefreshTest.php \
  docs/superpowers/specs/2026-07-21-settlement-refresh-on-deposit-design.md \
  docs/superpowers/plans/2026-07-21-settlement-refresh-on-deposit.md

git commit -m "$(cat <<'EOF'
fix(contracts): refresh settlement deposit summary on hold changes

EOF
)"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Listen `deposit-hold-registered` | Task 2 |
| Listen `deposit-hold-voided` | Task 2 |
| Re-render updates five summary lines | Task 1 + 2 |
| Do not reset wizard form state | Task 2 (empty handler) |
| No Action / service / Blade changes | (out of scope) |
| Feature tests | Task 1 |

## Self-review notes

- Exact UI labels confirmed: `deposit_paid` = «Depósito registrado», etc.
- Empty handler is intentional (same as Show).
- If isolated Livewire `dispatch` already re-renders without `#[On]`, listeners remain mandatory for real page sibling events from `DepositHoldForm`.
