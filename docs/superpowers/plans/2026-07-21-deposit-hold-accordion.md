# Deposit Hold Accordion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the «Depósito recibido» card on `contracts/{id}` collapsible (accordion) so it saves vertical space when the deposit is already complete, while staying open when remaining deposit is pending.

**Architecture:** Pure presentation with Alpine.js on `deposit-hold-form.blade.php`. Initial `open` comes from `$remainingDeposit > 0`. Header always shows title + summary (registered/remaining or green «Depósito completo») + chevron. Body (description, stats, table, form/banner) toggles with `x-show`. Void confirm modal stays outside `x-show`. No Livewire property for open state; no financial logic changes.

**Tech Stack:** Laravel 11, Livewire 4, Alpine.js (bundled with Livewire), Blade, PHPUnit via Sail, Pint.

## Global Constraints

- Run all commands via `./vendor/bin/sail` (never bare `php artisan` / `phpunit` / `pint`).
- Diff mínimo: only the deposit hold form view, i18n keys for a11y, and feature tests. Do not touch Actions, `DepositBalanceService`, or PDF receipt code.
- Spec: `docs/superpowers/specs/2026-07-21-deposit-hold-accordion-design.md`.
- Tests: `./vendor/bin/sail test --filter=DepositHoldForm`; format: `./vendor/bin/sail pint --dirty`.
- No commit unless the user explicitly asks (repo user rule).

---

## File Map

| File | Responsibility |
|------|----------------|
| `resources/views/livewire/contracts/deposit-hold-form.blade.php` | Accordion header + collapsible body; void modal outside `x-show` |
| `lang/es/contracts.php` | `deposit_panel_toggle` a11y label |
| `lang/en/contracts.php` | English pair |
| `tests/Feature/Contracts/DepositHoldFormTest.php` | Assert initial open/closed markup + header summary |
| `docs/superpowers/specs/2026-07-21-deposit-hold-accordion-design.md` | Mark Status: Approved (optional housekeeping in Task 3) |

---

### Task 1: Feature tests for accordion header defaults

**Files:**
- Modify: `tests/Feature/Contracts/DepositHoldFormTest.php`

**Interfaces:**
- Consumes: existing `makeContractWithUser()`, `DepositHoldForm`, `Charge::TYPE_DEPOSIT_HOLD`
- Produces: two new tests that lock the HTML contract for open/closed defaults

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Contracts/DepositHoldFormTest.php` (before `makeContractWithUser`):

```php
public function test_panel_starts_open_when_deposit_remaining(): void
{
    [$user, $contract] = $this->makeContractWithUser(depositAmount: 1000.0);

    Charge::factory()->create([
        'organization_id' => $contract->organization_id,
        'contract_id' => $contract->id,
        'unit_id' => $contract->unit_id,
        'type' => Charge::TYPE_DEPOSIT_HOLD,
        'period' => '2026-03',
        'charge_date' => '2026-03-01',
        'amount' => 400,
    ]);

    Livewire::actingAs($user)
        ->test(DepositHoldForm::class, ['contract' => $contract])
        ->assertSeeHtml('x-data="{ open: true }"')
        ->assertSeeHtml('aria-expanded="true"')
        ->assertSee('$400.00 / $600.00')
        ->assertDontSee(__('contracts.deposit_complete_title'), false);
}

public function test_panel_starts_closed_when_deposit_complete(): void
{
    [$user, $contract] = $this->makeContractWithUser(depositAmount: 500.0);

    Charge::factory()->create([
        'organization_id' => $contract->organization_id,
        'contract_id' => $contract->id,
        'unit_id' => $contract->unit_id,
        'type' => Charge::TYPE_DEPOSIT_HOLD,
        'period' => '2026-03',
        'charge_date' => '2026-03-01',
        'amount' => 500,
    ]);

    Livewire::actingAs($user)
        ->test(DepositHoldForm::class, ['contract' => $contract])
        ->assertSeeHtml('x-data="{ open: false }"')
        ->assertSeeHtml('aria-expanded="false"')
        ->assertSee(__('contracts.deposit_complete_title'))
        ->assertDontSee(__('contracts.register_deposit'));
}
```

Notes:
- `assertDontSee(__('contracts.deposit_complete_title'), false)` in the pending test: the second arg `false` means do **not** escape; use the escaped default if the helper signature in this Laravel version is `assertDontSee($value, $escape = true)`. Prefer simply asserting the amounts and `open: true` if `deposit_complete_title` could appear elsewhere — with pending remaining it must **not** appear at all.
- `$400.00 / $600.00` matches `number_format(..., 2)` already used in the view.

- [ ] **Step 2: Run tests to verify they fail**

Run:

```bash
./vendor/bin/sail test --filter='DepositHoldFormTest::test_panel_starts'
```

Expected: FAIL (missing `x-data="{ open: ... }"` / `aria-expanded` / header amounts).

- [ ] **Step 3: Stop — implementation is Task 2**

Do not implement the Blade yet in this task.

---

### Task 2: Accordion UI + a11y strings

**Files:**
- Modify: `resources/views/livewire/contracts/deposit-hold-form.blade.php`
- Modify: `lang/es/contracts.php`
- Modify: `lang/en/contracts.php`

**Interfaces:**
- Consumes: `$remainingDeposit`, `$registeredDeposit`, `$contractDepositAmount`, existing translations `deposit_received`, `deposit_complete_title`
- Produces: Alpine accordion markup matching Task 1 assertions

- [ ] **Step 1: Add i18n keys**

In `lang/es/contracts.php`, near `deposit_received`:

```php
'deposit_panel_toggle' => 'Mostrar u ocultar depósito recibido',
```

In `lang/en/contracts.php`, near `deposit_received`:

```php
'deposit_panel_toggle' => 'Show or hide deposit received',
```

- [ ] **Step 2: Rewrite the card shell with Alpine accordion**

Replace the contents of `resources/views/livewire/contracts/deposit-hold-form.blade.php` with this structure (preserve all existing inner blocks for stats/table/form/banner; only wrap and add header):

```blade
<x-ui.card>
    <div
        x-data="{ open: {{ $remainingDeposit > 0 ? 'true' : 'false' }} }"
        class="space-y-0"
    >
        <button
            type="button"
            class="flex w-full items-center justify-between gap-3 text-left"
            @click="open = !open"
            :aria-expanded="open.toString()"
            aria-controls="deposit-hold-panel"
            aria-expanded="{{ $remainingDeposit > 0 ? 'true' : 'false' }}"
            aria-label="{{ __('contracts.deposit_panel_toggle') }}"
        >
            <div class="min-w-0 flex-1">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('contracts.deposit_received') }}</h2>
                @if ($remainingDeposit > 0)
                    <p class="mt-0.5 text-sm text-slate-600">
                        ${{ number_format($registeredDeposit, 2) }} / ${{ number_format($remainingDeposit, 2) }}
                    </p>
                @else
                    <p class="mt-0.5 text-sm font-medium text-emerald-700">
                        {{ __('contracts.deposit_complete_title') }}
                    </p>
                @endif
            </div>
            <svg
                class="h-5 w-5 shrink-0 text-slate-500 transition-transform"
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

        <div
            id="deposit-hold-panel"
            x-show="open"
            x-cloak
            class="mt-4"
        >
            <p class="text-sm text-slate-600">
                {!! __('contracts.deposit_received_description', ['code' => '<code>DEPOSIT_HOLD</code>']) !!}
            </p>

            {{-- KEEP existing: 3-stat grid, @error deposit_general, holds table, complete banner OR register form --}}
            {{-- Do NOT move the void confirm-modal inside this x-show block --}}
        </div>
    </div>

    @if ($canManageCharges)
        <x-ui.confirm-modal
            :open="$showVoidConfirm"
            :title="__('contracts.void_deposit_title')"
            confirm-action="executeVoidDeposit"
            cancel-action="cancelVoidDeposit"
            :confirm-label="__('contracts.void_deposit')"
            :cancel-label="__('common.cancel')"
            :aria-label="__('contracts.void_deposit_title')"
        >
            <p class="text-slate-700">{{ __('contracts.void_deposit_confirm') }}</p>
        </x-ui.confirm-modal>
    @endif
</x-ui.card>
```

Implementation details (must follow):

1. Move the current `<h2>` + description out of the top; title goes in the button; description stays inside `#deposit-hold-panel`.
2. Cut-paste the existing grid / error / table / `@if ($remainingDeposit <= 0)` banner-or-form block **unchanged** into `#deposit-hold-panel` after the description.
3. Keep `<x-ui.confirm-modal>` as a **sibling** of the Alpine root (or after the Alpine root, still inside `<x-ui.card>`), never inside `x-show`.
4. Static `aria-expanded="{{ ... }}"` is required so PHPUnit sees the initial value without Alpine hydration; `:aria-expanded` keeps it in sync after clicks.
5. Ensure `x-data="{ open: true }"` / `false` has no extra spaces that would break `assertSeeHtml` (exact match from Task 1).

- [ ] **Step 3: Run accordion tests + full DepositHoldForm suite**

Run:

```bash
./vendor/bin/sail test --filter=DepositHoldForm
```

Expected: PASS (including existing tests and the two new ones).

If `test_form_is_hidden_when_deposit_is_complete` fails because `deposit_complete_title` now appears twice: that is fine for `assertSee`; it should still pass. If `assertDontSee` on register fails for another reason, fix the Blade so the register form still only renders when `$remainingDeposit > 0`.

- [ ] **Step 4: Format**

Run:

```bash
./vendor/bin/sail pint --dirty
```

Expected: no style issues (PHP/lang files only; Blade untouched by Pint).

- [ ] **Step 5: Commit only if the user asks**

Do not commit unless explicitly requested. If requested:

```bash
git add \
  resources/views/livewire/contracts/deposit-hold-form.blade.php \
  lang/es/contracts.php \
  lang/en/contracts.php \
  tests/Feature/Contracts/DepositHoldFormTest.php \
  docs/superpowers/specs/2026-07-21-deposit-hold-accordion-design.md \
  docs/superpowers/plans/2026-07-21-deposit-hold-accordion.md

git commit -m "$(cat <<'EOF'
feat(contracts): collapse deposit received card when complete

EOF
)"
```

---

### Task 3: Spec housekeeping + smoke check

**Files:**
- Modify: `docs/superpowers/specs/2026-07-21-deposit-hold-accordion-design.md` (Status → Approved)

**Interfaces:**
- None

- [ ] **Step 1: Mark spec approved**

In the spec header, change:

```markdown
**Status:** Draft
```

to:

```markdown
**Status:** Approved
```

- [ ] **Step 2: Manual smoke (optional but recommended)**

With Sail up, open a contract with pending deposit and one with complete deposit:

1. Pending: card open; header shows `$registered / $remaining`; chevron toggles body.
2. Complete: card closed; header shows green «Depósito completo»; expand shows historial + banner.
3. Void a complete hold: after re-render, card opens again (remaining > 0).
4. Register last remaining amount: after re-render, card closes.

- [ ] **Step 3: Final verification**

```bash
./vendor/bin/sail test --filter=DepositHoldForm
./vendor/bin/sail pint --dirty
```

Expected: all green.

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Initial open when remaining > 0 | Task 1 + 2 |
| Initial closed when remaining <= 0 | Task 1 + 2 |
| Header: title + registered/remaining | Task 2 |
| Header: green «Depósito completo» | Task 2 |
| Body = existing content | Task 2 |
| Modal outside `x-show` | Task 2 |
| Alpine only (no Livewire `$panelOpen`) | Task 2 |
| i18n for toggle a11y | Task 2 |
| Feature tests for markup defaults | Task 1 |
| No Action / balance service changes | (none — out of scope) |

## Self-review notes

- No placeholders / TBD left.
- Exact `x-data="{ open: true }"` string locked by tests — implementer must not add spaces or Blade whitespace that changes the HTML.
- Existing `test_form_is_hidden_when_deposit_is_complete` remains valid: register CTA still absent when complete; complete title still present (now also in header).
