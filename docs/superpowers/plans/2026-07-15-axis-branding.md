# AXIS Branding (Sidebar + Favicon) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace visible “Inmo Admin” branding with typographic **AXIS** mark + wordmark in sidebar, guest/auth screens, document title, and favicon.

**Architecture:** Add SVG brand assets under `public/`, a reusable `<x-ui.brand>` Blade component, wire it into sidebar and guest layout, update default titles / `APP_NAME` / auth copy, and cover with feature HTML assertions.

**Tech Stack:** Laravel 11, Blade, Tailwind 3, PHPUnit, Sail

**Spec:** `docs/superpowers/specs/2026-07-15-axis-branding-design.md`

## Global Constraints

- Product name in UI: **AXIS** (short wordmark only; expansion is docs-only)
- Mark: typographic monogram on indigo rounded square; favicon uses **AX** at small size
- Do not rename repo, package, DB, emails, or PDFs
- Do not change business domain copy except brand name strings that still say “Inmo Admin”
- Always run PHP/tests via `./vendor/bin/sail`
- Do not commit unless the user asks (except when a plan step says commit **and** the user chose plan execution that includes commits — still prefer asking if unclear)

---

## File map

| File | Action | Responsibility |
|------|--------|----------------|
| `public/images/brand/axis-mark.svg` | Create | Full AXIS mark for UI |
| `public/favicon.svg` | Create | Tab icon (AX) |
| `public/favicon.ico` | Replace | Non-empty favicon for legacy `/favicon.ico` requests |
| `resources/views/components/ui/brand.blade.php` | Create | Mark + wordmark (+ optional org) |
| `resources/views/layouts/partials/sidebar.blade.php` | Modify | Use `<x-ui.brand>` |
| `resources/views/layouts/app.blade.php` | Modify | Title default AXIS + icon links |
| `resources/views/layouts/guest.blade.php` | Modify | Brand, title, icon links |
| `resources/views/auth/login.blade.php` | Modify | Hardcoded h1 → AXIS |
| `resources/views/auth/register.blade.php` | Modify | Hardcoded h1 → AXIS |
| `config/app.php` | Modify | Default `APP_NAME` → `AXIS` |
| `lang/es/auth.php`, `lang/en/auth.php` | Modify | Titles `\| AXIS` |
| `lang/es/ui.php`, `lang/en/ui.php` | Modify | Guest description brand name |
| `tests/Feature/Brand/AxisBrandingTest.php` | Create | Branding assertions |
| `tests/Feature/Layout/AppShellRedesignTest.php` | Modify | Expect AXIS instead of Inmo Admin |

**Frozen:** business Livewire modules, PDF/email templates, prototypes under `docs/prototypes/` (historical)

---

### Task 1: Failing feature tests for AXIS branding

**Files:**
- Create: `tests/Feature/Brand/AxisBrandingTest.php`
- Modify: `tests/Feature/Layout/AppShellRedesignTest.php`

**Interfaces:**
- Consumes: existing `User::factory()`, `route('dashboard')`, `route('login')`
- Produces: failing tests that define success criteria for later tasks

- [ ] **Step 1: Create `tests/Feature/Brand/AxisBrandingTest.php`**

```php
<?php

namespace Tests\Feature\Brand;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AxisBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_shows_axis_brand_not_inmo_admin(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('AXIS', false);
        $response->assertSee('images/brand/axis-mark.svg', false);
        $response->assertDontSee('Inmo Admin', false);
    }

    public function test_app_layout_includes_favicon_links_and_axis_default_can_be_overridden(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('rel="icon"', false);
        $response->assertSee('favicon.svg', false);
    }

    public function test_login_page_shows_axis_brand_and_favicon(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('AXIS', false);
        $response->assertSee('favicon.svg', false);
        $response->assertSee('Login | AXIS', false);
        $response->assertDontSee('Inmo Admin', false);
    }

    public function test_favicon_ico_file_is_non_empty(): void
    {
        $path = public_path('favicon.ico');

        $this->assertFileExists($path);
        $this->assertGreaterThan(0, filesize($path));
    }
}
```

- [ ] **Step 2: Update `AppShellRedesignTest` assertion**

In `tests/Feature/Layout/AppShellRedesignTest.php`, change:

```php
$response->assertSee('Inmo Admin', false);
```

to:

```php
$response->assertSee('AXIS', false);
```

- [ ] **Step 3: Run tests — expect failure**

Run:

```bash
./vendor/bin/sail test --filter=AxisBrandingTest
./vendor/bin/sail test --filter=AppShellRedesignTest::test_authenticated_layout_renders_dark_sidebar_and_search
```

Expected: `AxisBrandingTest` fails (missing assets / still “Inmo Admin”). `AppShellRedesignTest` fails looking for AXIS.

- [ ] **Step 4: Commit** (only if user asked to commit during execution)

```bash
git add tests/Feature/Brand/AxisBrandingTest.php tests/Feature/Layout/AppShellRedesignTest.php
git commit -m "$(cat <<'EOF'
Add failing tests for AXIS branding surfaces.

EOF
)"
```

---

### Task 2: Brand SVG assets and favicon

**Files:**
- Create: `public/images/brand/axis-mark.svg`
- Create: `public/favicon.svg`
- Replace: `public/favicon.ico`

**Interfaces:**
- Consumes: none
- Produces: `/images/brand/axis-mark.svg`, `/favicon.svg`, non-empty `/favicon.ico`

- [ ] **Step 1: Create directory and mark SVG**

```bash
mkdir -p public/images/brand
```

Write `public/images/brand/axis-mark.svg`:

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" role="img" aria-label="AXIS">
  <rect width="32" height="32" rx="8" fill="#4f46e5"/>
  <text x="16" y="21"
        text-anchor="middle"
        font-family="ui-sans-serif, system-ui, -apple-system, Segoe UI, sans-serif"
        font-size="9"
        font-weight="700"
        letter-spacing="-0.04em"
        fill="#ffffff">AXIS</text>
</svg>
```

- [ ] **Step 2: Create favicon SVG (AX for legibility)**

Write `public/favicon.svg`:

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" role="img" aria-label="AXIS">
  <rect width="32" height="32" rx="8" fill="#4f46e5"/>
  <text x="16" y="22"
        text-anchor="middle"
        font-family="ui-sans-serif, system-ui, -apple-system, Segoe UI, sans-serif"
        font-size="12"
        font-weight="700"
        letter-spacing="-0.06em"
        fill="#ffffff">AX</text>
</svg>
```

- [ ] **Step 3: Replace empty `favicon.ico`**

Browsers still request `/favicon.ico`. Generate a small PNG-based ICO via Sail PHP (GD), then keep SVG as the preferred linked icon.

Run inside the project:

```bash
./vendor/bin/sail php -r '
$s = 32;
$im = imagecreatetruecolor($s, $s);
imagesavealpha($im, true);
$transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
imagefill($im, 0, 0, $transparent);
$indigo = imagecolorallocate($im, 79, 70, 229);
$white = imagecolorallocate($im, 255, 255, 255);
imagefilledrectangle($im, 0, 0, $s - 1, $s - 1, $indigo);
imagestring($im, 5, 6, 8, "AX", $white);
$png = sys_get_temp_dir() . "/axis-fav.png";
imagepng($im, $png);
imagedestroy($im);
// Minimal ICO wrapper around 32x32 PNG is overkill; copy PNG bytes to favicon.ico
// and also write a real PNG sibling for <link type="image/png">.
copy($png, "public/favicon-32.png");
copy($png, "public/favicon.ico");
unlink($png);
echo "ok\n";
'
```

If GD is missing in Sail, fall back to:

```bash
cp public/favicon.svg public/favicon.ico
```

only after confirming `filesize(public/favicon.ico) > 0`, and still ship `favicon.svg` as the primary `<link>`. Prefer the GD path so `/favicon.ico` is a raster.

Verify:

```bash
./vendor/bin/sail php -r 'echo filesize("public/favicon.ico"), PHP_EOL;'
```

Expected: integer `> 0`.

- [ ] **Step 4: Commit** (only if user asked)

```bash
git add public/images/brand/axis-mark.svg public/favicon.svg public/favicon.ico public/favicon-32.png
git commit -m "$(cat <<'EOF'
Add AXIS mark and favicon assets.

EOF
)"
```

---

### Task 3: `<x-ui.brand>` component

**Files:**
- Create: `resources/views/components/ui/brand.blade.php`

**Interfaces:**
- Consumes: `auth()->user()?->organization?->name`, asset `images/brand/axis-mark.svg`
- Produces: Blade component `<x-ui.brand variant="sidebar|guest" :show-org="bool" :href="?string" />`

- [ ] **Step 1: Create the component**

Write `resources/views/components/ui/brand.blade.php`:

```blade
@props([
    'variant' => 'sidebar', // sidebar | guest
    'showOrg' => false,
    'href' => null,
])

@php
    $isSidebar = $variant === 'sidebar';
    $markClass = $isSidebar ? 'h-8 w-8' : 'h-9 w-9';
    $wordClass = $isSidebar
        ? 'text-base font-semibold tracking-tight text-white'
        : 'text-xl font-semibold tracking-tight text-slate-900 dark:text-slate-100';
    $orgName = $showOrg ? (auth()->user()?->organization?->name) : null;
    $tag = $href ? 'a' : 'div';
@endphp

<div {{ $attributes->class(['flex items-center gap-2.5 min-w-0']) }}>
    <{{ $tag }}
        @if ($href) href="{{ $href }}" @endif
        class="flex min-w-0 items-center gap-2.5 {{ $href ? '' : '' }}"
    >
        <img
            src="{{ asset('images/brand/axis-mark.svg') }}"
            alt="AXIS"
            class="{{ $markClass }} shrink-0"
            width="32"
            height="32"
        >
        <span class="min-w-0">
            <span class="block {{ $wordClass }}">AXIS</span>
            @if ($orgName)
                <span class="block truncate text-xs text-slate-400">{{ $orgName }}</span>
            @endif
        </span>
    </{{ $tag }}>
</div>
```

- [ ] **Step 2: Smoke-check component compiles**

Run:

```bash
./vendor/bin/sail artisan view:cache
```

Expected: success (or clear with `view:clear` after). Then `./vendor/bin/sail artisan view:clear`.

- [ ] **Step 3: Commit** (only if user asked)

```bash
git add resources/views/components/ui/brand.blade.php
git commit -m "$(cat <<'EOF'
Add AXIS brand Blade component.

EOF
)"
```

---

### Task 4: Wire sidebar, layouts, and APP_NAME

**Files:**
- Modify: `resources/views/layouts/partials/sidebar.blade.php` (branding block ~lines 13–31)
- Modify: `resources/views/layouts/app.blade.php` (head)
- Modify: `resources/views/layouts/guest.blade.php` (head + left panel brand)
- Modify: `config/app.php` (`name` default)

**Interfaces:**
- Consumes: `<x-ui.brand>`
- Produces: AXIS visible in authenticated + guest HTML; favicon `<link>` tags present

- [ ] **Step 1: Replace sidebar branding block**

Replace the current branding `<div class="flex h-14 ...">` contents so the left side uses the component (keep the mobile close button):

```blade
{{-- ─── Branding ──────────────────────────────────────────────────────── --}}
<div class="flex h-14 shrink-0 items-center gap-2 border-b border-white/10 px-4">
    <x-ui.brand
        variant="sidebar"
        :show-org="true"
        :href="route('dashboard')"
        class="flex-1"
    />

    {{-- Close button – visible solo en mobile --}}
    <button id="sidebar-close-btn" type="button"
            class="rounded-lg p-1.5 text-slate-400 transition hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/30 lg:hidden"
            aria-label="{{ __('ui.nav.close_menu') }}">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </button>
</div>
```

- [ ] **Step 2: Update `app.blade.php` head**

Replace title and add icon links after charset/viewport:

```blade
<title>{{ $title ?? 'AXIS' }}</title>
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
```

Keep existing `@vite` and `@livewireStyles`.

- [ ] **Step 3: Update `guest.blade.php` head + brand in left panel**

Head:

```blade
<title>{{ $title ?? 'AXIS' }}</title>
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
```

Inside the left `<section class="hidden max-w-xl ...">`, **above** the badge paragraph, add:

```blade
<x-ui.brand variant="guest" class="mb-2" />
```

- [ ] **Step 4: Default APP_NAME**

In `config/app.php`:

```php
'name' => env('APP_NAME', 'AXIS'),
```

Do **not** edit `.env`.

- [ ] **Step 5: Run branding tests (partial pass expected)**

```bash
./vendor/bin/sail test --filter=AxisBrandingTest
```

Expected: sidebar/favicon file tests likely pass; login title may still fail until Task 5 updates lang/auth h1.

- [ ] **Step 6: Commit** (only if user asked)

```bash
git add resources/views/layouts/partials/sidebar.blade.php resources/views/layouts/app.blade.php resources/views/layouts/guest.blade.php config/app.php
git commit -m "$(cat <<'EOF'
Wire AXIS brand into app shell and guest layout.

EOF
)"
```

---

### Task 5: Auth copy — remove remaining “Inmo Admin”

**Files:**
- Modify: `resources/views/auth/login.blade.php`
- Modify: `resources/views/auth/register.blade.php`
- Modify: `lang/es/auth.php`, `lang/en/auth.php`
- Modify: `lang/es/ui.php`, `lang/en/ui.php`

**Interfaces:**
- Consumes: guest layout brand + titles
- Produces: no “Inmo Admin” on login/register HTML; titles end with `| AXIS`

- [ ] **Step 1: Replace hardcoded headings**

In `login.blade.php` and `register.blade.php`, change:

```blade
<h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">Inmo Admin</h1>
```

to:

```blade
<h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">AXIS</h1>
```

- [ ] **Step 2: Update auth title strings**

In both `lang/es/auth.php` and `lang/en/auth.php`, replace every `| Inmo Admin` with `| AXIS` in:

- `login.title`
- `register.title`
- `register.title_invite`
- `forgot_password.title`
- `reset_password.title`
- `verify_email.title`

- [ ] **Step 3: Update guest description brand name**

`lang/es/ui.php`:

```php
'description' => 'AXIS concentra la operación diaria en un panel claro para cobrar, cerrar periodos y monitorear métricas sin perder control.',
```

`lang/en/ui.php`:

```php
'description' => 'AXIS centralizes daily operations in a clear panel to collect rent, close periods, and monitor metrics without losing control.',
```

- [ ] **Step 4: Run full branding + shell tests**

```bash
./vendor/bin/sail test --filter=AxisBrandingTest
./vendor/bin/sail test --filter=AppShellRedesignTest
```

Expected: all PASS.

- [ ] **Step 5: Pint dirty PHP**

```bash
./vendor/bin/sail pint --dirty
```

- [ ] **Step 6: Commit** (only if user asked)

```bash
git add resources/views/auth/login.blade.php resources/views/auth/register.blade.php lang/es/auth.php lang/en/auth.php lang/es/ui.php lang/en/ui.php
git commit -m "$(cat <<'EOF'
Replace Inmo Admin auth copy with AXIS.

EOF
)"
```

---

### Task 6: Final verification

**Files:** none new (verification only)

- [ ] **Step 1: Grep runtime surfaces for leftover brand**

```bash
rg "Inmo Admin" resources/views lang config public -g '!docs/**'
```

Expected: no matches under `resources/views`, `lang`, `config`, `public`. (Historical docs/plans may still mention it — ignore.)

- [ ] **Step 2: Re-run targeted tests + related layout smoke**

```bash
./vendor/bin/sail test --filter=AxisBrandingTest
./vendor/bin/sail test --filter=AppShellRedesignTest
./vendor/bin/sail test --filter=LocaleSwitcherTest
```

Expected: all PASS (locale tests still find translated nav; brand change must not break them).

- [ ] **Step 3: Manual smoke checklist** (engineer)

1. Open http://127.0.0.1:8000/login — AXIS mark in left panel + form heading; tab icon visible  
2. Log in — sidebar top shows AXIS mark + wordmark (+ org name if any)  
3. Confirm browser tab icon is indigo AX square  

---

## Spec coverage self-review

| Spec requirement | Task |
|------------------|------|
| Typographic AXIS mark + wordmark | 2, 3 |
| Sidebar placement + org line | 3, 4 |
| Guest/auth brand | 4, 5 |
| Favicon SVG + non-empty ICO | 2, 4 |
| Default title AXIS | 4, 5 |
| APP_NAME default AXIS | 4 |
| Remove Inmo Admin from those surfaces | 4, 5, 6 |
| Feature HTML tests | 1, 5, 6 |
| Out of scope (repo/DB/email/PDF) | respected |

**Placeholder scan:** none.  
**Type consistency:** component props `variant`, `showOrg`, `href` used consistently in Tasks 3–4.
