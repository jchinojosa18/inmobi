# Settlement Accordion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the «Finiquito de contrato» card on `contracts/{id}` collapsible (accordion), starting closed, with the five deposit/outstanding summary figures always visible in the header.

**Architecture:** Pure presentation with Alpine.js on `settlement-wizard.blade.php`. Always `open: false` on load. Header = title + five summary lines + chevron. Body (`x-show`) = description, errors, form or ended message, last PDF banner. No Livewire property for open state; keep existing `onDepositHoldChanged` listeners unchanged.

**Tech Stack:** Laravel 11, Livewire 4, Alpine.js (bundled with Livewire), Blade, PHPUnit via Sail, Pint.

## Global Constraints

- Run all commands via `./vendor/bin/sail` (never bare `php artisan` / `phpunit` / `pint`).
- Diff mínimo: settlement wizard Blade, a11y i18n keys, and accordion feature tests. Do not touch Actions or `DepositBalanceService`.
- Spec: `docs/superpowers/specs/2026-07-21-settlement-accordion-design.md`.
- Existing `SettlementWizardDepositRefreshTest` must keep passing (summary labels/amounts stay in HTML outside `x-show`).
- Tests: `./vendor/bin/sail test --filter=SettlementWizard`; format: `./vendor/bin/sail pint --dirty`.
- No commit unless the user explicitly asks (repo user rule).

---

## File Map

| File | Responsibility |
|------|----------------|
| `resources/views/livewire/contracts/settlement-wizard.blade.php` | Accordion shell; 5 figures in header; body collapsible |
| `lang/es/contracts.php` | `settlement_panel_toggle` |
| `lang/en/contracts.php` | English pair |
| `tests/Feature/Contracts/SettlementWizardAccordionTest.php` | Initial closed markup + header summary labels |
| `docs/superpowers/specs/2026-07-21-settlement-accordion-design.md` | Already Approved |

---

### Task 1: Feature tests for closed accordion defaults

**Files:**
- Create: `tests/Feature/Contracts/SettlementWizardAccordionTest.php`

**Interfaces:**
- Consumes: `SettlementWizard`, existing factory patterns from `SettlementWizardDepositRefreshTest`
- Produces: RED tests locking `x-data="{ open: false }"`, `aria-expanded="false"`, five summary labels in HTML

- [ ] **Step 1: Write the failing test file**

Create `tests/Feature/Contracts/SettlementWizardAccordionTest.php`:

```php
<?php

namespace Tests\Feature\Contracts;

use App\Livewire\Contracts\SettlementWizard;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettlementWizardAccordionTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_starts_closed_with_summary_in_header(): void
    {
        [$user, $contract] = $this->makeContractWithUser(depositAmount: 10000.0);

        Livewire::actingAs($user)
            ->test(SettlementWizard::class, ['contract' => $contract])
            ->assertSeeHtml('x-data="{ open: false }"')
            ->assertSeeHtml('aria-expanded="false"')
            ->assertSee(__('contracts.settlement_title'))
            ->assertSee(__('contracts.deposit_paid'))
            ->assertSee(__('contracts.deposit_applied'))
            ->assertSee(__('contracts.deposit_refunded'))
            ->assertSee(__('contracts.available'))
            ->assertSee(__('contracts.current_outstanding'))
            ->assertSee(__('contracts.settlement_description'));
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
- `settlement_description` remains in the Blade HTML even when `x-show="false"` (Alpine keeps DOM); `assertSee` should still find it. That is fine — we only lock closed default + header labels.
- Exact string `x-data="{ open: false }"` must match implementation (no extra spaces).

- [ ] **Step 2: Run test expecting RED**

```bash
./vendor/bin/sail test --filter=SettlementWizardAccordion
```

Expected: FAIL (missing `x-data="{ open: false }"` / `aria-expanded="false"`).

- [ ] **Step 3: Stop — implementation is Task 2**

---

### Task 2: Accordion UI + a11y strings

**Files:**
- Modify: `resources/views/livewire/contracts/settlement-wizard.blade.php`
- Modify: `lang/es/contracts.php`
- Modify: `lang/en/contracts.php`

**Interfaces:**
- Consumes: `$paidDeposit`, `$appliedDeposit`, `$refundedDeposit`, `$availableDeposit`, `$currentOutstanding`, `$isEnded`, form/PDF blocks
- Produces: Alpine accordion markup matching Task 1

- [ ] **Step 1: Add i18n keys**

In `lang/es/contracts.php` near `settlement_title`:

```php
'settlement_panel_toggle' => 'Mostrar u ocultar finiquito de contrato',
```

In `lang/en/contracts.php`:

```php
'settlement_panel_toggle' => 'Show or hide contract settlement',
```

- [ ] **Step 2: Rewrite card shell**

Replace the top-level structure of `settlement-wizard.blade.php` with an Alpine accordion. Keep all existing form / ended / PDF logic; move the 5-line summary into the header (do **not** also keep a duplicate summary box in the body).

Target structure:

```blade
<x-ui.card>
    <div x-data="{ open: false }">
        <button
            type="button"
            class="flex w-full items-start justify-between gap-3 text-left"
            @click="open = !open"
            :aria-expanded="open.toString()"
            aria-controls="settlement-panel"
            aria-expanded="false"
            aria-label="{{ __('contracts.settlement_panel_toggle') }}"
        >
            <div class="min-w-0 flex-1">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('contracts.settlement_title') }}</h2>
                <div class="mt-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700">
                    <p>{{ __('contracts.deposit_paid') }}: <strong>${{ number_format($paidDeposit, 2) }}</strong></p>
                    <p>{{ __('contracts.deposit_applied') }}: <strong>${{ number_format($appliedDeposit, 2) }}</strong></p>
                    <p>{{ __('contracts.deposit_refunded') }}: <strong>${{ number_format($refundedDeposit, 2) }}</strong></p>
                    <p>{{ __('contracts.available') }}: <strong>${{ number_format($availableDeposit, 2) }}</strong></p>
                    <p>{{ __('contracts.current_outstanding') }}: <strong>${{ number_format($currentOutstanding, 2) }}</strong></p>
                </div>
            </div>
            <svg
                class="mt-1 h-5 w-5 shrink-0 text-slate-500 transition-transform"
                :class="{ 'rotate-180': open }"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
        </button>

        <div id="settlement-panel" x-show="open" x-cloak class="mt-4">
            <p class="text-sm text-slate-600">
                {{ __('contracts.settlement_description') }}
            </p>

            {{-- KEEP: @error settlement_general, isEnded block OR form, lastSettlementPdfUrl banner --}}
        </div>
    </div>
</x-ui.card>
```

Implementation details:

1. Remove the old flex header that put title+description left and summary right; summary moves into the toggle button.
2. Cut-paste existing error / form / ended / PDF blocks into `#settlement-panel` after the description — unchanged internals.
3. Exact `x-data="{ open: false }"` and static `aria-expanded="false"` for PHPUnit.
4. Do not remove `onDepositHoldChanged` from the PHP component.

- [ ] **Step 3: Run accordion + refresh suites**

```bash
./vendor/bin/sail test --filter=SettlementWizard
```

Expected: all PASS (`SettlementWizardAccordion` + `SettlementWizardDepositRefresh`).

- [ ] **Step 4: Format**

```bash
./vendor/bin/sail pint --dirty
```

- [ ] **Step 5: Commit only if the user asks**

```bash
git add \
  resources/views/livewire/contracts/settlement-wizard.blade.php \
  lang/es/contracts.php \
  lang/en/contracts.php \
  tests/Feature/Contracts/SettlementWizardAccordionTest.php \
  docs/superpowers/specs/2026-07-21-settlement-accordion-design.md \
  docs/superpowers/plans/2026-07-21-settlement-accordion.md

git commit -m "$(cat <<'EOF'
feat(contracts): collapse settlement card with summary header

EOF
)"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Always starts closed | Task 1 + 2 |
| Header: title + 5 figures + chevron | Task 2 |
| Body: description, form/ended, PDF | Task 2 |
| No duplicate summary in body | Task 2 |
| Alpine only | Task 2 |
| a11y toggle i18n | Task 2 |
| Refresh tests still pass | Task 2 verification |

## Self-review notes

- Exact `x-data="{ open: false }"` locked by tests.
- Summary in header ensures deposit-refresh assertions keep working without opening the panel.
- Re-render closing an open panel is accepted per spec (no Livewire `$panelOpen`).
