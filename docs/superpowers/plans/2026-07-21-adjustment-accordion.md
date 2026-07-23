# Adjustment Accordion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Collapse the «Crear ajuste» card on `contracts/{id}` with an Alpine accordion: closed by default, header = title + chevron only.

**Architecture:** Same pattern as deposit/settlement accordions. Wrap the existing adjustment card in `show.blade.php` with `x-data="{ open: false }"`. No Livewire accordion state; no Action changes.

**Tech Stack:** Laravel 11, Livewire 4, Alpine.js, Blade, PHPUnit via Sail, Pint.

## Global Constraints

- Run all commands via `./vendor/bin/sail`.
- Diff mínimo: `show.blade.php` adjustment card, a11y i18n, one feature test. Do not change adjustment business logic.
- Spec: `docs/superpowers/specs/2026-07-21-adjustment-accordion-design.md`.
- Tests: `./vendor/bin/sail test --filter=ContractShow`; format: `./vendor/bin/sail pint --dirty`.
- No commit unless the user explicitly asks.

---

## File Map

| File | Responsibility |
|------|----------------|
| `resources/views/livewire/contracts/show.blade.php` | Accordion shell around Crear ajuste card |
| `lang/es/contracts.php` | `adjustment_panel_toggle` |
| `lang/en/contracts.php` | English pair |
| `tests/Feature/Contracts/ContractShowAdjustmentAccordionTest.php` | Closed default markup |

---

### Task 1: Failing accordion test

**Files:**
- Create: `tests/Feature/Contracts/ContractShowAdjustmentAccordionTest.php`

**Interfaces:**
- Consumes: `App\Livewire\Contracts\Show`, factories like `ContractShowTest`
- Produces: RED test locking `x-data="{ open: false }"`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/Contracts/ContractShowAdjustmentAccordionTest.php` mirroring helpers from `tests/Feature/Contracts/ContractShowTest.php` (read that file for exact setup: user with `charges.manage`, active contract). Minimal test:

```php
<?php

namespace Tests\Feature\Contracts;

use App\Livewire\Contracts\Show;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContractShowAdjustmentAccordionTest extends TestCase
{
    use RefreshDatabase;

    public function test_adjustment_panel_starts_closed(): void
    {
        [$user, $contract] = $this->makeContractWithManageChargesUser();

        Livewire::actingAs($user)
            ->test(Show::class, ['contract' => $contract])
            ->assertSeeHtml('x-data="{ open: false }"')
            ->assertSeeHtml('aria-expanded="false"')
            ->assertSee(__('contracts.create_adjustment'));
    }

    /**
     * @return array{0: User, 1: Contract}
     */
    private function makeContractWithManageChargesUser(): array
    {
        // Copy pattern from ContractShowTest / DepositHoldFormTest:
        // Organization, Property, Unit, Tenant, Contract (active), User in same org.
        // Ensure user can manage charges so the adjustment card renders (@if $canManageCharges).
        // Prefer the same permission bootstrap used in ContractShowTest if present;
        // otherwise actingAs Admin-capable user as in DepositHoldFormTest ($this->actingAs($user)).

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

**Important:** The settlement accordion also uses `x-data="{ open: false }"`. If `Show` embeds both, `assertSeeHtml('x-data="{ open: false }"')` may already pass because of Finiquito. To lock **this** card specifically, also assert a unique marker:

- Prefer asserting `aria-controls="adjustment-panel"` and `id="adjustment-panel"` (add these in Task 2).
- Update the test to:

```php
->assertSeeHtml('aria-controls="adjustment-panel"')
->assertSeeHtml('id="adjustment-panel"')
->assertSeeHtml('aria-expanded="false"')
->assertSee(__('contracts.create_adjustment'));
```

(Optionally keep one `x-data="{ open: false }"` assert if useful.)

- [ ] **Step 2: Run RED**

```bash
./vendor/bin/sail test --filter=ContractShowAdjustmentAccordion
```

Expected: FAIL until `adjustment-panel` exists.

- [ ] **Step 3: Stop**

---

### Task 2: Accordion UI + i18n

**Files:**
- Modify: `resources/views/livewire/contracts/show.blade.php` (Crear ajuste card only, ~lines 51–98)
- Modify: `lang/es/contracts.php`, `lang/en/contracts.php`

**Interfaces:**
- Produces: Alpine accordion with unique `adjustment-panel` id

- [ ] **Step 1: i18n**

ES near `create_adjustment`:

```php
'adjustment_panel_toggle' => 'Mostrar u ocultar crear ajuste',
```

EN:

```php
'adjustment_panel_toggle' => 'Show or hide create adjustment',
```

- [ ] **Step 2: Wrap card**

Replace the Crear ajuste `<x-ui.card>` contents with:

```blade
@if ($canManageCharges)
    <x-ui.card>
        <div x-data="{ open: false }">
            <button
                type="button"
                class="flex w-full items-center justify-between gap-3 text-left"
                @click="open = !open"
                :aria-expanded="open.toString()"
                aria-controls="adjustment-panel"
                aria-expanded="false"
                aria-label="{{ __('contracts.adjustment_panel_toggle') }}"
            >
                <h2 class="text-lg font-semibold text-slate-900">{{ __('contracts.create_adjustment') }}</h2>
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

            <div id="adjustment-panel" x-show="open" x-cloak class="mt-4">
                <p class="text-sm text-slate-600">
                    {{ __('contracts.adjustment_description') }}
                </p>

                {{-- KEEP existing form wire:submit="createAdjustment" unchanged --}}
            </div>
        </div>
    </x-ui.card>
@endif
```

Move description + form into `#adjustment-panel`. Do not alter form fields/wire models.

- [ ] **Step 3: GREEN**

```bash
./vendor/bin/sail test --filter=ContractShow
./vendor/bin/sail pint --dirty
```

- [ ] **Step 4: Commit only if user asks**

---

## Spec coverage

| Requirement | Task |
|-------------|------|
| Closed by default | 1+2 |
| Header title + chevron only | 2 |
| Body = description + form | 2 |
| a11y toggle | 2 |
| Unique panel id (vs settlement) | 1+2 |

## Self-review

- Must use `adjustment-panel` so tests do not false-pass via settlement accordion’s `open: false`.
