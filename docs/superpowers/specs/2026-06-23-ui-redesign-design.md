# UI Redesign — Design Spec

**Date:** 2026-06-23  
**Status:** Approved  
**Prototype:** `docs/prototypes/2026-06-23-ui-redesign.html`  
**Plan:** `docs/superpowers/plans/2026-06-23-ui-redesign-phase1.md`

## Goal

Elevate the authenticated app shell and key operational screens to a more formal, polished SaaS aesthetic while preserving operational density for daily financial work. Align the interior experience with the quality of the existing auth screens **without modifying auth flows or views**.

## Design Direction

**B + C hybrid:** Professional refined SaaS (Linear/Stripe sensibility) as the base, with operational/enterprise density for tables, filters, and financial workflows.

**Phased delivery:** Design system + layout shell + three pilot screens in Phase 1; gradual rollout to remaining modules in Phase 2.

## Out of Scope

- `resources/views/layouts/guest.blade.php`
- All auth views: `login`, `register`, `forgot-password`, `reset-password`, `verify-email`
- Business logic, Livewire queries, permissions, routes
- PDF templates (`resources/views/pdf/`)
- Email templates (`resources/views/emails/`)

## Design Tokens

| Token | Value | Usage |
|-------|-------|-------|
| App background | `bg-slate-50` (optional subtle dot texture via CSS, lighter than guest) | Main content area |
| Surface | `bg-white` + `border-slate-200/80` | Cards, modals, dropdowns |
| Sidebar | `bg-slate-900`, text `slate-300`, active `bg-white/10 text-white` | Primary navigation |
| Primary action | `bg-slate-900 text-white` hover `slate-800` | CTAs, submit buttons |
| Accent / active nav | `indigo-600` / `indigo-400` on dark sidebar | Active links, focus rings, links |
| Success / warning / error | `emerald`, `amber`, `red` (match auth flash styles) | Financial status, alerts |
| Typography | Figtree (unchanged) | All app text |
| Border radius | `rounded-lg` inputs, `rounded-xl` cards, `rounded-2xl` modals | Unified radii |
| Shadows | `shadow-sm` cards, `shadow-lg` modals and dropdowns | Subtle elevation |

Extend `tailwind.config.js` with semantic color aliases if needed (e.g. `sidebar`, `accent`) to avoid magic class strings in components.

## Shell Changes

### `layouts/app.blade.php`

- Keep structure: sidebar overlay, fixed sidebar, main area with topbar.
- Update body/background classes to match token table.
- Unify session flash messages (success/error) with icon + refined border/background (auth-adjacent style, no dark mode required in app shell for Phase 1).
- Toast styles: align with new surface tokens (`slate-900` background retained or softened to `slate-800`).

### Sidebar (`layouts/partials/sidebar.blade.php`)

- Dark sidebar (`slate-900`): logo “Inmo Admin” in white, optional organization name in `slate-400`.
- Nav links: inactive `text-slate-400 hover:bg-white/5 hover:text-slate-200`; active `bg-white/10 text-white font-medium`.
- Section labels: `text-[11px] uppercase tracking-widest text-slate-500`.
- Icons: inactive `text-slate-500`, active `text-indigo-400`.
- CTA “Nuevo contrato”: `bg-white text-slate-900` or `bg-indigo-600 text-white` — use **indigo** for primary CTA in sidebar footer.
- Mobile close button: light icon on dark background.

### Topbar (`layouts/partials/topbar.blade.php`)

- White background, `border-b border-slate-200/80`, sticky.
- Command palette trigger: pill shape, refined border, `bg-slate-50`.
- Plaza selector and user dropdown: match new input/dropdown component styles.
- Desktop “Nuevo contrato” button: primary variant from component library.

## Component Library (Phase 1)

Location: `resources/views/components/ui/`

| Component | Props / variants | Purpose |
|-----------|------------------|---------|
| `card` | optional `title`, `padding` | Content containers, filter panels |
| `button` | `variant`: primary, secondary, ghost, danger; `size`: sm, md | All actions |
| `input` | `label`, `error`, `id` | Text inputs with consistent label styling |
| `select` | `label`, `error`, `id` | Selects with consistent label styling |
| `badge` | `variant`: success, warning, danger, neutral, info | Status chips (cobranza urgency, contract state) |
| `page-header` | `title`, `description`; slot `actions` | Page title block used on every index |
| `table` | slot `head`, slot `body`; optional `compact` | Wrapped `<table>` with styled thead, row hover |
| `stat-card` | `label`, `value`, optional `trend` / `hint` | Dashboard KPI tiles |
| `empty-state` | `title`, `description`, optional action slot | Zero-result lists |

**Update existing:**

- `modal.blade.php` — apply `rounded-2xl`, refined header border, button ghost for close.
- `confirm-modal.blade.php` — align with modal + button variants.

**CSS:** Minimal `@layer components` in `resources/css/app.css` only for patterns that are awkward as Blade (e.g. sidebar scrollbar, optional subtle app background texture). Prefer Tailwind utilities inside components.

## Phase 1 — Pilot Screens

### Dashboard (`livewire/dashboard/index.blade.php`)

- Replace inline header with `<x-ui.page-header>`.
- KPI blocks → `<x-ui.stat-card>` grid.
- Onboarding checklist → `<x-ui.card>` with progress bar (keep existing Livewire logic).
- Any embedded tables → `<x-ui.table compact>`.

### Cobranza (`livewire/cobranza/index.blade.php`)

- Page header component.
- Filters in `<x-ui.card>`.
- Table with urgency badges (`badge` variants: danger for overdue, warning for grace, neutral otherwise).
- Preserve all wire:model filters and permission gates.

### Contratos (`livewire/contracts/index.blade.php`)

- Same pattern: page-header + filter card + operational table.
- Translate filter option labels to Spanish where still in English (`Active` → `Activos`, etc.) as part of UX polish in this screen only.
- “Nuevo contrato” uses `<x-ui.button variant="primary">`.

## Phase 2 — Rollout (completed 2026-06-23)

Migrated remaining Livewire views in module batches:

1. **Catálogos:** properties, houses/show, units, tenants ✅
2. **Finanzas:** expenses, reports/cash-flow, month-closes, payments/* ✅
3. **Sistema:** settings/*, admin/system-status, documents, search/command-palette, quick-register modals, contracts detail/forms ✅

Each batch replaced inline Tailwind with shared components without changing behavior.

## Implementation Approach

**Recommended:** Option 1 — design tokens in Tailwind + Blade UI components.

- Create components first, then migrate layout shell, then three pilot screens.
- Do not refactor Livewire PHP classes unless required for passing props to components.
- Keep diffs focused: no unrelated formatting or logic changes.

## Verification

```bash
./vendor/bin/sail test
./vendor/bin/sail pint --dirty
```

Manual smoke:

- Login (unchanged) → dashboard, cobranza, contratos
- Sidebar navigation active states on dark background
- Mobile drawer open/close
- Command palette ⌘K
- Quick payment / expense modals still open from layout globals

## Success Criteria

- Auth views byte-identical (no edits to guest layout or auth blades).
- Shell (sidebar + topbar) feels formal and distinct from content area.
- Dashboard, cobranza, and contratos use shared components consistently.
- No test regressions; no permission or business logic changes.
- Remaining screens unchanged in Phase 1 but visually acceptable next to new shell (transitional inconsistency accepted until Phase 2).

## Decisions Log

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Aesthetic | B + C hybrid | Matches auth quality + daily ops density |
| Delivery | Phased | Lower risk, reviewable diffs |
| Sidebar | Dark (`slate-900`) | More formal; separates nav from content |
| Accent color | Indigo | SaaS polish without clashing with slate |
| Component strategy | Blade UI library | Reusable across 30+ views in Phase 2 |
| Auth | Frozen | User requirement; already satisfactory |
