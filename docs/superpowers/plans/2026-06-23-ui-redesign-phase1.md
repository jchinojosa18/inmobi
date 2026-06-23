# UI Redesign Phase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the approved visual redesign (dark sidebar, UI component library, refined shell) on dashboard, cobranza, and contratos — without touching auth views.

**Architecture:** Extend Tailwind tokens, add Blade UI components under `resources/views/components/ui/`, migrate layout shell first, then three pilot Livewire views. Match prototype `docs/prototypes/2026-06-23-ui-redesign.html`. No Livewire PHP logic changes unless required for component slots.

**Tech Stack:** Laravel 11, Livewire 4, Tailwind 3, Blade components, PHPUnit, Sail

**Spec:** `docs/superpowers/specs/2026-06-23-ui-redesign-design.md`  
**Prototype:** `docs/prototypes/2026-06-23-ui-redesign.html`

---

## File map

| File | Action | Responsibility |
|------|--------|----------------|
| `tailwind.config.js` | Modify | Semantic font/colors if needed |
| `resources/css/app.css` | Modify | Sidebar scrollbar, optional subtle bg |
| `resources/views/components/ui/button.blade.php` | Create | Primary/secondary/ghost/danger buttons |
| `resources/views/components/ui/badge.blade.php` | Create | Status chips |
| `resources/views/components/ui/card.blade.php` | Create | Surface container |
| `resources/views/components/ui/input.blade.php` | Create | Labeled text input |
| `resources/views/components/ui/select.blade.php` | Create | Labeled select |
| `resources/views/components/ui/page-header.blade.php` | Create | Title + description + actions slot |
| `resources/views/components/ui/stat-card.blade.php` | Create | Dashboard KPI tile |
| `resources/views/components/ui/table.blade.php` | Create | Styled table wrapper |
| `resources/views/components/ui/empty-state.blade.php` | Create | Zero-results placeholder |
| `resources/views/components/ui/modal.blade.php` | Modify | Align with prototype tokens |
| `resources/views/components/ui/confirm-modal.blade.php` | Modify | Use button component classes |
| `resources/views/layouts/app.blade.php` | Modify | Dark sidebar shell, flash messages |
| `resources/views/layouts/partials/sidebar.blade.php` | Modify | Dark nav, indigo CTA |
| `resources/views/layouts/partials/topbar.blade.php` | Modify | Pill search, refined controls |
| `resources/views/livewire/dashboard/index.blade.php` | Modify | Components migration |
| `resources/views/livewire/cobranza/index.blade.php` | Modify | Components migration |
| `resources/views/livewire/contracts/index.blade.php` | Modify | Components + Spanish labels |
| `tests/Feature/Layout/AppShellRedesignTest.php` | Create | Shell + component smoke tests |

**Frozen (do not edit):** `layouts/guest.blade.php`, `resources/views/auth/*`

---

### Task 1: Tailwind tokens and base CSS

**Files:**
- Modify: `tailwind.config.js`
- Modify: `resources/css/app.css`

- [ ] **Step 1: Extend Tailwind config**

```js
// tailwind.config.js — inside theme.extend
colors: {
    accent: {
        DEFAULT: '#4f46e5', // indigo-600
        light: '#818cf8',   // indigo-400
    },
},
```

- [ ] **Step 2: Add minimal app.css utilities**

```css
/* resources/css/app.css — after @tailwind utilities */
@layer utilities {
    .sidebar-scrollbar::-webkit-scrollbar { width: 6px; }
    .sidebar-scrollbar::-webkit-scrollbar-thumb {
        @apply rounded-full bg-white/20;
    }
}
```

- [ ] **Step 3: Rebuild assets**

Run: `./vendor/bin/sail npm run build`  
Expected: build succeeds

---

### Task 2: Core UI components (button, badge, card)

**Files:**
- Create: `resources/views/components/ui/button.blade.php`
- Create: `resources/views/components/ui/badge.blade.php`
- Create: `resources/views/components/ui/card.blade.php`

- [ ] **Step 1: Create button component**

```blade
@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
])

@php
    $base = 'inline-flex items-center justify-center gap-1.5 font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none';
    $sizes = [
        'sm' => 'rounded-lg px-3 py-1.5 text-xs',
        'md' => 'rounded-lg px-4 py-2 text-sm',
    ];
    $variants = [
        'primary' => 'bg-slate-900 text-white hover:bg-slate-800',
        'secondary' => 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
        'ghost' => 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
        'danger' => 'border border-red-200 bg-white text-red-700 hover:bg-red-50',
        'accent' => 'bg-indigo-600 text-white hover:bg-indigo-500',
    ];
    $classes = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
@endphp

<button {{ $attributes->merge(['type' => $type, 'class' => $classes]) }}>
    {{ $slot }}
</button>
```

- [ ] **Step 2: Create badge component**

```blade
@props(['variant' => 'neutral'])

@php
    $variants = [
        'success' => 'bg-emerald-50 text-emerald-700',
        'warning' => 'bg-amber-50 text-amber-700',
        'danger' => 'bg-red-50 text-red-700',
        'info' => 'bg-sky-50 text-sky-700',
        'neutral' => 'bg-slate-100 text-slate-700',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium '.($variants[$variant] ?? $variants['neutral'])]) }}>
    {{ $slot }}
</span>
```

- [ ] **Step 3: Create card component**

```blade
@props(['title' => null, 'padding' => true])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200/80 bg-white shadow-sm'.($padding ? ' p-5' : '')]) }}>
    @if ($title)
        <h2 class="mb-4 text-lg font-semibold text-slate-900">{{ $title }}</h2>
    @endif
    {{ $slot }}
</div>
```

---

### Task 3: Form and layout components

**Files:**
- Create: `resources/views/components/ui/input.blade.php`
- Create: `resources/views/components/ui/select.blade.php`
- Create: `resources/views/components/ui/page-header.blade.php`
- Create: `resources/views/components/ui/stat-card.blade.php`
- Create: `resources/views/components/ui/table.blade.php`
- Create: `resources/views/components/ui/empty-state.blade.php`

- [ ] **Step 1: input.blade.php**

```blade
@props(['label' => null, 'error' => null, 'id' => null])

<div>
    @if ($label)
        <label @if($id) for="{{ $id }}" @endif class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</label>
    @endif
    <input
        @if($id) id="{{ $id }}" @endif
        {{ $attributes->merge(['class' => 'w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100'.($error ? ' border-red-300' : '')]) }}
    />
    @if ($error)
        <p class="mt-1 text-xs text-red-600">{{ $error }}</p>
    @endif
</div>
```

- [ ] **Step 2: select.blade.php** — same label/error pattern; merge class on `<select>`.

- [ ] **Step 3: page-header.blade.php**

```blade
@props(['title', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-start justify-between gap-4']) }}>
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-sm text-slate-600">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
```

- [ ] **Step 4: stat-card.blade.php**

Props: `label`, `value`, `hint` (optional), `tone` (`default|success|warning|danger`).

Border/bg tint per tone; value in `text-2xl font-semibold`.

- [ ] **Step 5: table.blade.php**

```blade
@props(['compact' => false])

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm']) }}>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50/80 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                    {{ $head }}
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                {{ $body }}
            </tbody>
        </table>
    </div>
</div>
```

Row hover: document that `<tr>` in consuming views should include `class="transition hover:bg-slate-50/80"`.

- [ ] **Step 6: empty-state.blade.php**

Centered text, title `text-sm font-medium`, description `text-xs text-slate-500`, optional `$action` slot.

---

### Task 4: Update existing modals

**Files:**
- Modify: `resources/views/components/ui/modal.blade.php`
- Modify: `resources/views/components/ui/confirm-modal.blade.php`

- [ ] **Step 1: modal.blade.php** — change outer panel to `rounded-2xl border border-slate-200/80 shadow-lg`; header `border-slate-100`; close button uses ghost styling (`rounded-lg p-1.5 hover:bg-slate-100`).

- [ ] **Step 2: confirm-modal.blade.php** — footer buttons: replace inline classes with `<x-ui.button variant="secondary">` and `<x-ui.button variant="danger">` where wire:click is preserved via attributes.

---

### Task 5: App layout shell

**Files:**
- Modify: `resources/views/layouts/app.blade.php`

- [ ] **Step 1: Sidebar container** — change aside classes from `bg-white border-r border-slate-200` to `bg-slate-900 border-r border-white/10`.

- [ ] **Step 2: Flash messages** — add leading SVG icon; use `rounded-xl` and borders matching auth (`border-emerald-200 bg-emerald-50` / `border-red-200 bg-red-50`). Keep same session keys and text output.

- [ ] **Step 3: Command palette JS** — update selected row highlight from `bg-slate-100` to `bg-indigo-50` for consistency (in inline script `cpApplySelection`).

- [ ] **Step 4: Toasts** — optional: `bg-slate-800` instead of `slate-900`.

---

### Task 6: Dark sidebar partial

**Files:**
- Modify: `resources/views/layouts/partials/sidebar.blade.php`

- [ ] **Step 1: Replace PHP nav class helpers**

```php
$active   = 'bg-white/10 text-white font-medium';
$inactive = 'text-slate-400 hover:bg-white/5 hover:text-slate-200';
$aIcon    = 'text-indigo-400';
$iIcon    = 'text-slate-500 group-hover:text-slate-300';
```

- [ ] **Step 2: Branding block** — white logo text; below it `@if(auth()->user()->organization?->name)` org name in `text-xs text-slate-400`.

- [ ] **Step 3: Header border** — `border-white/10`; close button `text-slate-400 hover:bg-white/10 hover:text-white`.

- [ ] **Step 4: CTA footer** — `bg-indigo-600 hover:bg-indigo-500` full-width button; border-top `border-white/10`.

- [ ] **Step 5: Add `sidebar-scrollbar` class** to `<nav>`.

Match structure/icons from prototype; preserve all `@can`, routes, and permission gates unchanged.

---

### Task 7: Topbar partial

**Files:**
- Modify: `resources/views/layouts/partials/topbar.blade.php`

- [ ] **Step 1: Header** — `border-b border-slate-200/80`.

- [ ] **Step 2: Search trigger** — `rounded-full border border-slate-200 bg-slate-50 hover:border-slate-300`.

- [ ] **Step 3: Plaza select** — `rounded-lg border-slate-200 focus:ring-indigo-100 focus:border-indigo-400`.

- [ ] **Step 4: User avatar** — `bg-indigo-600` instead of `bg-slate-900`.

- [ ] **Step 5: Dropdown** — `rounded-xl border-slate-200/80 shadow-lg`.

Preserve `id="topbar-plaza-select"` and all form/action behavior.

---

### Task 8: Dashboard view migration

**Files:**
- Modify: `resources/views/livewire/dashboard/index.blade.php`

- [ ] **Step 1: Replace page header** with:

```blade
<x-ui.page-header
    title="Dashboard operativo"
    description="Centro de control operativo para administración diaria."
>
    <x-slot:actions>
        {{-- existing permission-gated buttons, using x-ui.button --}}
    </x-slot:actions>
</x-ui.page-header>
```

**Important:** Keep exact description string — tests assert `Centro de control operativo`.

- [ ] **Step 2: KPI grid** — replace six `<article>` blocks with `<x-ui.stat-card>` using appropriate `tone` props. Keep same `$incomeMonth`, `$expenseMonth`, etc. values and number_format.

- [ ] **Step 3: Onboarding section** — wrap in `<x-ui.card>`; progress bar fill `bg-indigo-600`; checklist items keep all `@if`, `wire:click`, routes.

- [ ] **Step 4: Embedded tables** (Vencidos, En gracia, Pagos recientes) — wrap with `<x-ui.table>` slots; preserve column headers text tests depend on.

Do not change Livewire component PHP.

---

### Task 9: Cobranza view migration

**Files:**
- Modify: `resources/views/livewire/cobranza/index.blade.php`

- [ ] **Step 1: page-header** with existing title/description.

- [ ] **Step 2: Filters** — `<x-ui.card :padding="true">` with grid; replace raw inputs with `<x-ui.input>` / `<x-ui.select>` preserving all `wire:model.live*` attributes via `$attributes` merge on components (pass `wire:model` on component tag).

- [ ] **Step 3: Tabs** — keep wire:click; active tab styles: vencidos `bg-amber-50 text-amber-800`, gracia `bg-sky-50 text-sky-700`, corriente `bg-emerald-50 text-emerald-700`.

- [ ] **Step 4: Table** — `<x-ui.table>`; urgency days use `<x-ui.badge variant="danger|warning|success">`.

Preserve all table columns and Livewire loops.

---

### Task 10: Contratos view migration

**Files:**
- Modify: `resources/views/livewire/contracts/index.blade.php`

- [ ] **Step 1: page-header** + `<x-ui.button variant="primary" wire:click="$dispatch('open-contract-create')">` (if button stays wire:click).

- [ ] **Step 2: Filter card** with input/select components.

- [ ] **Step 3: Translate filter options**

| Before | After |
|--------|-------|
| Active | Activos |
| Ended | Finalizados |
| All | Todos |

- [ ] **Step 4: Table + badges** for cobranza urgency column using `<x-ui.badge>`.

---

### Task 11: Tests and verification

**Files:**
- Create: `tests/Feature/Layout/AppShellRedesignTest.php`

- [ ] **Step 1: Write shell smoke test**

```php
<?php

namespace Tests\Feature\Layout;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppShellRedesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_layout_renders_dark_sidebar_and_search(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Inmo Admin', false);
        $response->assertSee('bg-slate-900', false);
        $response->assertSee('Buscar', false);
        $response->assertSee('Dashboard operativo', false);
    }

    public function test_cobranza_page_renders_with_new_table_wrapper(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('cobranza.index'));

        $response->assertOk();
        $response->assertSee('Cobranza', false);
    }
}
```

- [ ] **Step 2: Run targeted tests**

```bash
./vendor/bin/sail test --filter=AppShellRedesign
./vendor/bin/sail test --filter=DashboardControlCenter
./vendor/bin/sail test --filter=DashboardOnboardingChecklist
./vendor/bin/sail test --filter=TopbarPlazaSelector
./vendor/bin/sail test --filter=CommandPalette
./vendor/bin/sail test --filter=QuickRegisterModal
```

Expected: all PASS

- [ ] **Step 3: Format**

```bash
./vendor/bin/sail pint --dirty
```

- [ ] **Step 4: Manual smoke** (logged in via browser)

1. Login page unchanged visually
2. Dashboard KPIs and onboarding render
3. Sidebar active state on dark background
4. Mobile drawer opens/closes
5. ⌘K command palette
6. Cobranza tabs and filters work
7. Contratos filters show Spanish labels

---

## Phase 2 (out of this plan)

Separate plan/PRs for: properties, tenants, expenses, reports, settings, modals (quick-register, command-palette). Do not start until Phase 1 is merged.

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Design tokens | Task 1 |
| Component library | Tasks 2–3 |
| Modal updates | Task 4 |
| app.blade.php shell | Task 5 |
| Dark sidebar | Task 6 |
| Topbar | Task 7 |
| Dashboard pilot | Task 8 |
| Cobranza pilot | Task 9 |
| Contratos pilot | Task 10 |
| Auth frozen | Enforced in all tasks |
| Verification | Task 11 |

---

## Risks and mitigations

| Risk | Mitigation |
|------|------------|
| `wire:model` on Blade components | Pass wire attributes on component tag; merge in input/select |
| Tests assert old CSS/text | Keep dashboard description string; run full dashboard test suite |
| Transitional old screens vs new shell | Accepted per spec until Phase 2 |
| Confirm modal + wire:click on button component | Use `<x-ui.button wire:click="...">` attribute forwarding |
