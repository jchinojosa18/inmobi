# AXIS Branding (Sidebar + Favicon) — Design Spec

**Date:** 2026-07-15  
**Status:** Approved  
**Product name:** AXIS (Asset Exchange & Information System)

## Goal

Replace the visible “Inmo Admin” brand with **AXIS**: a typographic monogram in the sidebar, on auth (guest) screens, in the browser tab title, and as the favicon.

## Out of Scope

- Renaming the git repo, package name, or database
- Changing domain/business copy (cobranza, contratos, inmuebles, etc.)
- Email or PDF templates
- Custom per-organization logos
- Full marketing site / landing redesign beyond guest layout brand mark

## Brand Decisions

| Decision | Choice |
|----------|--------|
| Mark | Typographic monogram (no illustration) |
| Letters (UI mark + wordmark) | **AXIS** |
| Letters (favicon at 16px) | **AX** if full “AXIS” is illegible; otherwise AXIS |
| Product name in UI | Short wordmark **AXIS** (not the full expansion) |
| Expansion (docs only) | Asset Exchange & Information System |
| Previous name | Remove “Inmo Admin” from sidebar, guest brand area, and default `<title>` |
| Scope | Authenticated app + guest (login/register) + favicon + title |

## Visual Spec

### Mark

- Rounded square badge (~32×32 px in sidebar)
- Background: indigo accent consistent with existing sidebar (`indigo` family used for active nav icons)
- Letters: semibold, tight tracking, high contrast (white/light on indigo)
- Same mark reused in guest layout (may scale slightly larger)

### Wordmark

- Text **AXIS** next to the mark
- Authenticated sidebar: keep organization name underneath when present (unchanged behavior)
- Guest: mark + wordmark only (no org name)

### Favicon

- SVG primary (`favicon.svg`) based on the mark
- Replace empty `public/favicon.ico` with a real ICO (or PNG fallback) so older browsers still show an icon
- Layouts link explicitly to the icon assets

### Page title

- Default title in `app` and `guest` layouts: **AXIS**
- Existing `$title` override still works when a page passes one

## Implementation Shape

### New assets

- `public/images/brand/axis-mark.svg` — reusable mark
- `public/favicon.svg` — tab icon
- `public/favicon.ico` — replace 0-byte placeholder

### New component

- `resources/views/components/ui/brand.blade.php`
  - Renders mark + optional wordmark
  - Variants: `sidebar` (dark background) and `guest` (light/dark guest panel)
  - Optional org line only for sidebar when the user has an organization

### Wire-up

| File | Change |
|------|--------|
| `resources/views/layouts/partials/sidebar.blade.php` | Replace “Inmo Admin” text block with `<x-ui.brand />` |
| `resources/views/layouts/guest.blade.php` | Show brand; default title AXIS; favicon links |
| `resources/views/layouts/app.blade.php` | Default title AXIS; favicon links |
| `config/app.php` | Default `APP_NAME` → `AXIS` (env override still wins) |

## Testing

- Feature/view smoke: authenticated dashboard HTML contains AXIS brand (not “Inmo Admin”) in sidebar region
- Guest login page HTML contains AXIS brand and favicon `<link>`
- Default document title is AXIS when no `$title` is set
- No need for browser E2E; assert HTML strings / presence of icon links

## Success Criteria

1. Sidebar top shows AXIS mark + wordmark (org name still below when applicable)
2. Login/register show AXIS brand
3. Browser tab shows AXIS favicon and default title “AXIS”
4. No remaining “Inmo Admin” in those three surfaces
