# Locale Switcher (i18n Phase 1) — Design Spec

**Date:** 2026-06-23  
**Status:** Approved  
**Related:** `docs/superpowers/specs/2026-06-23-ui-redesign-design.md`

## Goal

Add a polished ES/EN language switcher and the i18n infrastructure needed to change the application locale at runtime. Phase 1 translates the **app shell** (sidebar, topbar, global toasts) and **auth screens** so the switch has immediate, visible effect. Operational Livewire screens remain in Spanish until Phase 2.

## Out of Scope (Phase 1)

- Livewire module views (cobranza, contratos, finanzas, settings forms, etc.)
- PDF templates (`resources/views/pdf/`)
- Email templates (`resources/views/emails/`)
- Backend validation messages in Actions/Livewire (Phase 2+)
- Command palette result labels and quick-register modals (Phase 2)
- More than two locales

## Supported Locales

| Code | Label in UI | Default |
|------|-------------|---------|
| `es` | ES | **Yes** (app is Spanish-first today) |
| `en` | EN | |

Change `config/app.php` default `locale` to `es` and `fallback_locale` to `es`. English strings fall back to Spanish if a key is missing during rollout.

## Architecture

### Resolution order (middleware)

```
1. Authenticated user → users.locale (if valid)
2. Session key locale (guest or before user column populated)
3. config('app.locale') → es
```

### `SetLocale` middleware

- New class: `app/Http/Middleware/SetLocale.php`
- Registered in `bootstrap/app.php` on the `web` stack **before** `SetTenantOrganization` (locale is user/session scoped, not tenant scoped)
- Calls `app()->setLocale($locale)` and sets `Carbon` locale if used in shell
- Validates locale against allowlist `['es', 'en']`; invalid values ignored

### Persistence

| Context | Storage | Notes |
|---------|---------|-------|
| Guest | `session('locale')` | Survives until session expires |
| Authenticated | `users.locale` column + session mirror | Column is source of truth on login |

**Migration:** `users.locale` — `string`, length 5, nullable, default `null`. When `null`, fall back to session then config.

**On login:** If user has `locale` set, write it to session so middleware stays consistent.

### Route

```
POST /locale   name: locale.update
```

- Controller: `app/Http/Controllers/LocaleController.php` (thin)
- Validates `locale` ∈ `{es, en}`
- Updates session always; updates `users.locale` when authenticated
- Redirects back (`return back()`)
- No auth required (guests on login page can switch)
- CSRF protected (standard web middleware)

## UI Component

### `resources/views/components/ui/locale-switcher.blade.php`

Segmented pill control — two submit buttons in one form, matching topbar tokens:

```
┌─────────────────────────┐
│  [ ES ]    EN           │  ← active: white bg, shadow-sm, slate-900 text
└─────────────────────────┘     inactive: slate-500 text, hover slate-700
```

**Styling:**

- Container: `inline-flex rounded-full border border-slate-200 bg-slate-50 p-0.5`
- Active segment: `rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-slate-900 shadow-sm`
- Inactive segment: `rounded-full px-2.5 py-1 text-xs font-medium text-slate-500 hover:text-slate-700`
- `aria-label`: `__('ui.language')` — "Idioma" / "Language"
- Each button: `name="locale" value="es|en"`, `type="submit"`
- `aria-current="true"` on active locale button

**Props:** none required; reads `app()->getLocale()` for active state.

**Behavior:** Single `<form method="POST" action="{{ route('locale.update') }}">` with `@csrf`. Clicking inactive locale submits immediately (no JS). Full page reload applies new locale — acceptable for Phase 1; keeps implementation simple and accessible.

### Placement

| Location | Position |
|----------|----------|
| `topbar.blade.php` | Right cluster, **before** plaza selector / user menu |
| `guest.blade.php` | Fixed top-right: `absolute right-4 top-4 z-10 sm:right-6 sm:top-6` |

Guest placement does not alter auth card layout (UI redesign spec: auth views frozen aesthetically — only additive switcher overlay).

## Translation Files

Laravel `lang/{locale}/*.php` structure:

| File | Contents |
|------|----------|
| `lang/es/ui.php` + `lang/en/ui.php` | Shell: nav labels, section headers, topbar, toasts, aria-labels |
| `lang/es/auth.php` + `lang/en/auth.php` | Login, register, forgot/reset password, verify email |
| `lang/es/messages.php` + `lang/en/messages.php` | Flash messages set from routes/controllers in Phase 1 scope |

**Key naming:** dot notation, grouped by area — e.g. `ui.nav.dashboard`, `auth.login.submit`, `messages.email_verified`.

**Usage in Blade:** `{{ __('ui.nav.dashboard') }}` — no new helper classes.

### Shell strings to translate (sidebar)

Section headers: Operación, Catálogos, Finanzas, Sistema.

Nav items: Dashboard, Cobranza, Contratos, Propiedades, Inquilinos, Egresos, Reporte flujo, Cierres, Configuración, Roles y permisos, Invitaciones, Plazas, Auditoría, Admin System, Nuevo contrato.

Aria-labels: menú principal, abrir/cerrar menú.

### Topbar strings

Buscar…, Plaza:, Todas, Nuevo contrato, Salir, aria-labels for search and user menu.

### App layout strings

Global toasts: "Egreso registrado correctamente." (and cp-notify messages that originate from shell — defer cp strings to Phase 2 if they come from Livewire PHP).

### Auth strings (all 5 views)

Login, register, forgot-password, reset-password, verify-email — labels, placeholders, buttons, links, error headings, guest panel marketing copy in `guest.blade.php`.

### Route/controller flash messages in scope

- Login invalid credentials (`routes/web.php`)
- Email verified success
- Password reset flows (controllers under `app/Http/Controllers/Auth/`)

Use `__('messages.*')` in PHP; do not translate arbitrary user-generated content.

## Layout Updates

| File | Change |
|------|--------|
| `layouts/app.blade.php` | `lang="{{ str_replace('_', '-', app()->getLocale()) }}"` |
| `layouts/guest.blade.php` | Already dynamic; add `<x-ui.locale-switcher />` |
| `layouts/partials/sidebar.blade.php` | Replace hardcoded strings with `__()` |
| `layouts/partials/topbar.blade.php` | Replace hardcoded strings; include switcher |
| `auth/*.blade.php` | Replace hardcoded strings with `__()` |
| `guest.blade.php` marketing panel | Replace hardcoded strings with `__()` |

## User Model

Add `locale` to `$fillable`. No cast needed (string).

## Testing

| Test | Type |
|------|------|
| `SetLocale` middleware applies user locale | Feature |
| `SetLocale` falls back session → config | Feature |
| `POST /locale` updates session for guest | Feature |
| `POST /locale` updates `users.locale` when auth | Feature |
| Invalid locale rejected (422) | Feature |
| Sidebar renders English nav when locale=en | Feature (assertSee) |

```bash
./vendor/bin/sail test --filter=Locale
./vendor/bin/sail pint --dirty
```

Manual smoke:

- Guest: switch EN on login → labels change; switch back ES
- Login → topbar switcher persists after refresh
- Logout → session locale retained for guest flow
- Sidebar active states unchanged

## Phase 2 Rollout (completed 2026-06-23)

Migrated Livewire modules in batches:

1. **Dashboard + command palette** ✅
2. **Cobranza + contratos** ✅
3. **Catálogos + finanzas** ✅
4. **Settings + admin + documents** ✅

Each batch: extracted strings to `lang/{locale}/{module}.php`, replaced in Blade/PHP, added feature test spot-checks.

## Decisions Log

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Default locale | `es` | Matches current UI and users |
| Fallback | `es` | Missing EN keys show Spanish, not broken keys |
| Persistence | `users.locale` + session | Guests + cross-device for logged-in users |
| Switcher UX | Form submit, no JS | Accessible, simple, reliable |
| Switcher style | Segmented pill | Matches topbar `slate-50` search pill aesthetic |
| Auth views | Translate strings only | Layout frozen per UI redesign spec |
| Phase 1 scope | Shell + auth | Visible impact without 30+ view diff |

## Success Criteria

- ES/EN switch visible in topbar (auth) and guest layout (login)
- Changing locale updates sidebar, topbar, and auth text immediately
- Preference persists for logged-in users across sessions
- No business logic or permission changes
- Tests pass; Pint clean
- Operational screens translate with locale switch (Phase 2 complete)
