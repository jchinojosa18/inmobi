# Locale Switcher (i18n Phase 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add ES/EN locale infrastructure, a segmented pill switcher in topbar/guest layout, and translate the app shell + auth screens so language changes are immediately visible.

**Architecture:** `SetLocale` middleware resolves locale from `users.locale` → session → config (`es`). `POST /locale` updates both session and user column. Blade strings move to `lang/{es,en}/{ui,auth,messages}.php`. No Livewire or business-logic changes.

**Tech Stack:** Laravel 11, Blade components, PHPUnit, Sail

**Spec:** `docs/superpowers/specs/2026-06-23-locale-switcher-design.md`

---

## File map

| File | Action | Responsibility |
|------|--------|----------------|
| `config/app.php` | Modify | Default `locale` and `fallback_locale` → `es` |
| `database/migrations/xxxx_add_locale_to_users_table.php` | Create | `users.locale` column |
| `app/Models/User.php` | Modify | Add `locale` to `$fillable` |
| `app/Http/Middleware/SetLocale.php` | Create | Resolve and apply locale |
| `bootstrap/app.php` | Modify | Register middleware before `SetTenantOrganization` |
| `app/Http/Controllers/LocaleController.php` | Create | Handle `POST /locale` |
| `routes/web.php` | Modify | Route, login locale sync, translated flash messages |
| `lang/es/ui.php` | Create | Spanish shell strings |
| `lang/en/ui.php` | Create | English shell strings |
| `lang/es/auth.php` | Create | Spanish auth strings |
| `lang/en/auth.php` | Create | English auth strings |
| `lang/es/messages.php` | Create | Spanish flash/validation messages (Phase 1 scope) |
| `lang/en/messages.php` | Create | English flash/validation messages |
| `resources/views/components/ui/locale-switcher.blade.php` | Create | Segmented ES/EN pill |
| `resources/views/layouts/partials/topbar.blade.php` | Modify | `__()` strings + switcher |
| `resources/views/layouts/partials/sidebar.blade.php` | Modify | `__()` nav strings |
| `resources/views/layouts/app.blade.php` | Modify | Dynamic `lang`, toast `__()` |
| `resources/views/layouts/guest.blade.php` | Modify | Switcher + marketing `__()` |
| `resources/views/auth/login.blade.php` | Modify | `__()` strings |
| `resources/views/auth/register.blade.php` | Modify | `__()` strings |
| `resources/views/auth/forgot-password.blade.php` | Modify | `__()` strings |
| `resources/views/auth/reset-password.blade.php` | Modify | `__()` strings |
| `resources/views/auth/verify-email.blade.php` | Modify | `__()` strings |
| `app/Http/Controllers/Auth/PasswordResetController.php` | Modify | `__()` validation + flash |
| `app/Http/Controllers/Auth/RegisterController.php` | Modify | `__()` validation messages |
| `tests/Feature/Locale/LocaleSwitcherTest.php` | Create | Middleware, route, shell i18n tests |

---

### Task 1: Config and database foundation

**Files:**
- Modify: `config/app.php`
- Create: `database/migrations/2026_06_23_000001_add_locale_to_users_table.php`
- Modify: `app/Models/User.php`

- [ ] **Step 1: Update default locale in config**

In `config/app.php`, change:

```php
'locale' => env('APP_LOCALE', 'es'),
'fallback_locale' => env('APP_FALLBACK_LOCALE', 'es'),
```

- [ ] **Step 2: Create migration**

```bash
./vendor/bin/sail artisan make:migration add_locale_to_users_table --table=users
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
```

- [ ] **Step 3: Run migration**

Run: `./vendor/bin/sail artisan migrate`  
Expected: migration runs successfully

- [ ] **Step 4: Add `locale` to User model**

```php
protected $fillable = [
    'organization_id',
    'name',
    'email',
    'locale',
    'password',
];
```

---

### Task 2: SetLocale middleware (TDD)

**Files:**
- Create: `app/Http/Middleware/SetLocale.php`
- Modify: `bootstrap/app.php`
- Create: `tests/Feature/Locale/LocaleSwitcherTest.php`

- [ ] **Step 1: Write failing middleware tests**

Create `tests/Feature/Locale/LocaleSwitcherTest.php`:

```php
<?php

namespace Tests\Feature\Locale;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSwitcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_to_spanish_when_no_preference(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'));

        $this->assertSame('es', app()->getLocale());
    }

    public function test_user_locale_column_takes_priority(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)->get(route('dashboard'));

        $this->assertSame('en', app()->getLocale());
    }

    public function test_session_locale_used_for_guest(): void
    {
        $response = $this->withSession(['locale' => 'en'])->get(route('login'));

        $response->assertOk();
        $this->assertSame('en', app()->getLocale());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/sail test --filter=LocaleSwitcherTest`  
Expected: FAIL (locale stays `en` from phpunit or middleware missing)

- [ ] **Step 3: Implement SetLocale middleware**

Create `app/Http/Middleware/SetLocale.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /** @var list<string> */
    private const SUPPORTED = ['es', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolveLocale($request));

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $userLocale = $request->user()?->locale;
        if (is_string($userLocale) && in_array($userLocale, self::SUPPORTED, true)) {
            return $userLocale;
        }

        if ($request->hasSession()) {
            $sessionLocale = $request->session()->get('locale');
            if (is_string($sessionLocale) && in_array($sessionLocale, self::SUPPORTED, true)) {
                return $sessionLocale;
            }
        }

        $default = (string) config('app.locale', 'es');

        return in_array($default, self::SUPPORTED, true) ? $default : 'es';
    }
}
```

- [ ] **Step 4: Register middleware in bootstrap/app.php**

```php
$middleware->web(prepend: [
    \App\Http\Middleware\SetLocale::class,
]);
```

Keep existing `append` for `SetTenantOrganization` and `CaptureAuditReason`.

- [ ] **Step 5: Run middleware tests**

Run: `./vendor/bin/sail test --filter=LocaleSwitcherTest`  
Expected: PASS (3 tests)

---

### Task 3: Locale route and controller (TDD)

**Files:**
- Create: `app/Http/Controllers/LocaleController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/Locale/LocaleSwitcherTest.php`

- [ ] **Step 1: Add failing route tests**

Append to `LocaleSwitcherTest.php`:

```php
public function test_guest_can_switch_locale_via_post(): void
{
    $response = $this->post(route('locale.update'), ['locale' => 'en']);

    $response->assertRedirect();
    $response->assertSessionHas('locale', 'en');
}

public function test_authenticated_user_persists_locale_to_database(): void
{
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('locale.update'), ['locale' => 'en'])
        ->assertRedirect();

    $this->assertSame('en', $user->fresh()->locale);
}

public function test_invalid_locale_is_rejected(): void
{
    $this->post(route('locale.update'), ['locale' => 'fr'])
        ->assertSessionHasErrors('locale');
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/sail test --filter=LocaleSwitcherTest`  
Expected: FAIL (route not defined)

- [ ] **Step 3: Create LocaleController**

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:es,en'],
        ]);

        $locale = $validated['locale'];
        $request->session()->put('locale', $locale);

        if ($user = $request->user()) {
            $user->forceFill(['locale' => $locale])->save();
        }

        return back();
    }
}
```

- [ ] **Step 4: Register route in routes/web.php**

Add near top (after `use` imports):

```php
use App\Http\Controllers\LocaleController;
```

Add before guest group:

```php
Route::post('/locale', LocaleController::class)->name('locale.update');
```

- [ ] **Step 5: Sync locale on login**

In `routes/web.php` login closure, after `$request->session()->regenerate();`:

```php
if ($locale = Auth::user()?->locale) {
    $request->session()->put('locale', $locale);
}
```

- [ ] **Step 6: Run route tests**

Run: `./vendor/bin/sail test --filter=LocaleSwitcherTest`  
Expected: PASS (6 tests)

---

### Task 4: Translation files

**Files:**
- Create: `lang/es/ui.php`, `lang/en/ui.php`
- Create: `lang/es/auth.php`, `lang/en/auth.php`
- Create: `lang/es/messages.php`, `lang/en/messages.php`

- [ ] **Step 1: Create Spanish UI translations**

`lang/es/ui.php`:

```php
<?php

return [
    'language' => 'Idioma',
    'nav' => [
        'main' => 'Menú principal',
        'open_menu' => 'Abrir menú de navegación',
        'close_menu' => 'Cerrar menú de navegación',
        'open_menu_short' => 'Abrir menú',
        'sections' => [
            'operation' => 'Operación',
            'catalogs' => 'Catálogos',
            'finance' => 'Finanzas',
            'system' => 'Sistema',
        ],
        'dashboard' => 'Dashboard',
        'cobranza' => 'Cobranza',
        'contracts' => 'Contratos',
        'properties' => 'Propiedades',
        'tenants' => 'Inquilinos',
        'expenses' => 'Egresos',
        'cash_flow_report' => 'Reporte flujo',
        'month_closes' => 'Cierres',
        'settings' => 'Configuración',
        'roles' => 'Roles y permisos',
        'invitations' => 'Invitaciones',
        'plazas' => 'Plazas',
        'audit' => 'Auditoría',
        'admin_system' => 'Admin System',
        'new_contract' => 'Nuevo contrato',
    ],
    'topbar' => [
        'search' => 'Buscar…',
        'search_aria' => 'Abrir búsqueda rápida (⌘K)',
        'plaza' => 'Plaza:',
        'all_plazas' => 'Todas',
        'logout' => 'Salir',
    ],
    'toasts' => [
        'expense_created' => 'Egreso registrado correctamente.',
    ],
    'guest' => [
        'badge' => 'Plataforma inmobiliaria',
        'headline' => 'Opera tu cartera con disciplina financiera y trazabilidad real.',
        'description' => 'Inmo Admin concentra la operación diaria en un panel claro para cobrar, cerrar periodos y monitorear métricas sin perder control.',
        'bullets' => [
            'Cobranza priorizada por vencimiento y periodo de gracia.',
            'Multas automáticas con cálculo diario compuesto.',
            'Reportes y cierres mensuales listos para auditoría.',
        ],
    ],
];
```

- [ ] **Step 2: Create English UI translations**

`lang/en/ui.php`:

```php
<?php

return [
    'language' => 'Language',
    'nav' => [
        'main' => 'Main menu',
        'open_menu' => 'Open navigation menu',
        'close_menu' => 'Close navigation menu',
        'open_menu_short' => 'Open menu',
        'sections' => [
            'operation' => 'Operations',
            'catalogs' => 'Catalogs',
            'finance' => 'Finance',
            'system' => 'System',
        ],
        'dashboard' => 'Dashboard',
        'cobranza' => 'Collections',
        'contracts' => 'Contracts',
        'properties' => 'Properties',
        'tenants' => 'Tenants',
        'expenses' => 'Expenses',
        'cash_flow_report' => 'Cash flow report',
        'month_closes' => 'Month closes',
        'settings' => 'Settings',
        'roles' => 'Roles & permissions',
        'invitations' => 'Invitations',
        'plazas' => 'Plazas',
        'audit' => 'Audit log',
        'admin_system' => 'Admin System',
        'new_contract' => 'New contract',
    ],
    'topbar' => [
        'search' => 'Search…',
        'search_aria' => 'Open quick search (⌘K)',
        'plaza' => 'Plaza:',
        'all_plazas' => 'All',
        'logout' => 'Sign out',
    ],
    'toasts' => [
        'expense_created' => 'Expense recorded successfully.',
    ],
    'guest' => [
        'badge' => 'Real estate platform',
        'headline' => 'Run your portfolio with financial discipline and real traceability.',
        'description' => 'Inmo Admin centralizes daily operations in a clear panel to collect rent, close periods, and monitor metrics without losing control.',
        'bullets' => [
            'Collections prioritized by due date and grace period.',
            'Automatic penalties with daily compound calculation.',
            'Reports and monthly closes ready for audit.',
        ],
    ],
];
```

- [ ] **Step 3: Create Spanish auth translations**

`lang/es/auth.php`:

```php
<?php

return [
    'login' => [
        'title' => 'Login | Inmo Admin',
        'badge' => 'LOGIN',
        'password' => 'Contraseña',
        'password_placeholder' => 'Ingresa tu contraseña',
        'show_password' => 'Mostrar contraseña',
        'hide_password' => 'Ocultar contraseña',
        'remember' => 'Recordarme',
        'forgot_password' => '¿Olvidaste tu contraseña?',
        'submit' => 'Entrar',
        'submitting' => 'Entrando...',
        'no_account' => '¿No tienes cuenta?',
        'create_account' => 'Crear cuenta',
        'check_errors' => 'Revisa los datos e intenta nuevamente.',
    ],
    'register' => [
        'title' => 'Crear cuenta | Inmo Admin',
        'title_invite' => 'Crear cuenta | Inmo Admin',
        'badge' => 'CREAR CUENTA',
        'badge_invite' => 'ACEPTAR INVITACIÓN',
        'subtitle' => 'Configura tu empresa y entra al panel en minutos.',
        'subtitle_invite' => 'Completa tu cuenta para unirte a tu empresa.',
        'check_errors' => 'Revisa los datos e intenta nuevamente.',
    ],
    'forgot_password' => [
        'title' => 'Recuperar contraseña | Inmo Admin',
        'badge' => 'RECUPERAR ACCESO',
        'heading' => '¿Olvidaste tu contraseña?',
        'description' => 'Ingresa tu email y te enviaremos un enlace para restablecer tu contraseña.',
        'submit' => 'Enviar enlace de recuperación',
        'back_to_login' => 'Volver al login',
        'check_errors' => 'Revisa los datos e intenta nuevamente.',
    ],
    'reset_password' => [
        'title' => 'Nueva contraseña | Inmo Admin',
        'badge' => 'NUEVA CONTRASEÑA',
        'heading' => 'Restablecer contraseña',
        'description' => 'Elige una contraseña nueva para tu cuenta.',
        'new_password' => 'Nueva contraseña',
        'new_password_placeholder' => 'Mínimo 8 caracteres',
        'confirm_password' => 'Confirmar contraseña',
        'confirm_password_placeholder' => 'Repite tu contraseña',
        'submit' => 'Guardar contraseña',
        'check_errors' => 'Revisa los datos e intenta nuevamente.',
    ],
    'verify_email' => [
        'title' => 'Verifica tu correo | Inmo Admin',
        'badge' => 'VERIFICACIÓN',
        'heading' => 'Verifica tu correo',
        'description' => 'Te enviamos un enlace de verificación a tu email. Debes confirmarlo para acceder al panel.',
        'resend' => 'Reenviar enlace',
        'logout' => 'Cerrar sesión',
        'link_sent' => 'Te enviamos un enlace nuevo de verificación.',
    ],
    'email' => 'Email',
    'email_placeholder' => 'tu@email.com',
];
```

- [ ] **Step 4: Create English auth translations**

`lang/en/auth.php`:

```php
<?php

return [
    'login' => [
        'title' => 'Login | Inmo Admin',
        'badge' => 'LOGIN',
        'password' => 'Password',
        'password_placeholder' => 'Enter your password',
        'show_password' => 'Show password',
        'hide_password' => 'Hide password',
        'remember' => 'Remember me',
        'forgot_password' => 'Forgot your password?',
        'submit' => 'Sign in',
        'submitting' => 'Signing in...',
        'no_account' => "Don't have an account?",
        'create_account' => 'Create account',
        'check_errors' => 'Please check your details and try again.',
    ],
    'register' => [
        'title' => 'Create account | Inmo Admin',
        'title_invite' => 'Create account | Inmo Admin',
        'badge' => 'CREATE ACCOUNT',
        'badge_invite' => 'ACCEPT INVITATION',
        'subtitle' => 'Set up your company and access the panel in minutes.',
        'subtitle_invite' => 'Complete your account to join your company.',
        'check_errors' => 'Please check your details and try again.',
    ],
    'forgot_password' => [
        'title' => 'Reset password | Inmo Admin',
        'badge' => 'RECOVER ACCESS',
        'heading' => 'Forgot your password?',
        'description' => 'Enter your email and we will send you a link to reset your password.',
        'submit' => 'Send reset link',
        'back_to_login' => 'Back to login',
        'check_errors' => 'Please check your details and try again.',
    ],
    'reset_password' => [
        'title' => 'New password | Inmo Admin',
        'badge' => 'NEW PASSWORD',
        'heading' => 'Reset password',
        'description' => 'Choose a new password for your account.',
        'new_password' => 'New password',
        'new_password_placeholder' => 'At least 8 characters',
        'confirm_password' => 'Confirm password',
        'confirm_password_placeholder' => 'Repeat your password',
        'submit' => 'Save password',
        'check_errors' => 'Please check your details and try again.',
    ],
    'verify_email' => [
        'title' => 'Verify your email | Inmo Admin',
        'badge' => 'VERIFICATION',
        'heading' => 'Verify your email',
        'description' => 'We sent a verification link to your email. You must confirm it to access the panel.',
        'resend' => 'Resend link',
        'logout' => 'Sign out',
        'link_sent' => 'We sent a new verification link.',
    ],
    'email' => 'Email',
    'email_placeholder' => 'you@email.com',
];
```

- [ ] **Step 5: Create messages files (flash + validation in scope)**

`lang/es/messages.php`:

```php
<?php

return [
    'invalid_credentials' => 'Las credenciales proporcionadas no son validas.',
    'email_verified' => 'Correo verificado correctamente.',
    'verification_link_sent' => 'Te enviamos un enlace nuevo de verificación.',
    'password_reset_link_sent' => 'Si el email está registrado, recibirás un enlace para restablecer tu contraseña.',
    'password_updated' => 'Tu contraseña fue actualizada. Ya puedes iniciar sesión.',
    'password_reset_invalid_token' => 'El enlace de recuperación no es válido o expiró.',
    'password_reset_invalid_user' => 'No encontramos un usuario con ese email.',
    'password_reset_failed' => 'No pudimos restablecer tu contraseña. Intenta solicitar un enlace nuevo.',
    'validation' => [
        'email_required' => 'El email es obligatorio.',
        'email_invalid' => 'El email no es válido.',
        'password_required' => 'La contraseña es obligatoria.',
        'password_min' => 'La contraseña debe tener al menos 8 caracteres.',
        'password_confirmed' => 'La confirmación de contraseña no coincide.',
        'organization_name_required' => 'El nombre de la empresa es obligatorio.',
        'organization_name_unique' => 'Ese nombre de empresa ya está registrado.',
        'name_required' => 'El nombre es obligatorio.',
        'email_unique' => 'Este email ya está registrado.',
        'invite_invalid' => 'La invitación no es válida o expiró.',
        'invite_email_mismatch' => 'El email debe coincidir con la invitación recibida.',
    ],
];
```

`lang/en/messages.php`:

```php
<?php

return [
    'invalid_credentials' => 'The provided credentials are not valid.',
    'email_verified' => 'Email verified successfully.',
    'verification_link_sent' => 'We sent a new verification link.',
    'password_reset_link_sent' => 'If the email is registered, you will receive a link to reset your password.',
    'password_updated' => 'Your password was updated. You can sign in now.',
    'password_reset_invalid_token' => 'The recovery link is invalid or has expired.',
    'password_reset_invalid_user' => 'We could not find a user with that email.',
    'password_reset_failed' => 'We could not reset your password. Please request a new link.',
    'validation' => [
        'email_required' => 'Email is required.',
        'email_invalid' => 'Email is not valid.',
        'password_required' => 'Password is required.',
        'password_min' => 'Password must be at least 8 characters.',
        'password_confirmed' => 'Password confirmation does not match.',
        'organization_name_required' => 'Company name is required.',
        'organization_name_unique' => 'That company name is already registered.',
        'name_required' => 'Name is required.',
        'email_unique' => 'This email is already registered.',
        'invite_invalid' => 'The invitation is invalid or has expired.',
        'invite_email_mismatch' => 'Email must match the invitation you received.',
    ],
];
```

---

### Task 5: Locale switcher component

**Files:**
- Create: `resources/views/components/ui/locale-switcher.blade.php`

- [ ] **Step 1: Create component**

```blade
@php
    $current = app()->getLocale();
    $active = 'rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-slate-900 shadow-sm';
    $inactive = 'rounded-full px-2.5 py-1 text-xs font-medium text-slate-500 hover:text-slate-700';
@endphp

<form
    method="POST"
    action="{{ route('locale.update') }}"
    class="inline-flex rounded-full border border-slate-200 bg-slate-50 p-0.5"
    aria-label="{{ __('ui.language') }}"
>
    @csrf
    <button
        type="submit"
        name="locale"
        value="es"
        @if ($current === 'es') aria-current="true" @endif
        class="{{ $current === 'es' ? $active : $inactive }}"
    >ES</button>
    <button
        type="submit"
        name="locale"
        value="en"
        @if ($current === 'en') aria-current="true" @endif
        class="{{ $current === 'en' ? $active : $inactive }}"
    >EN</button>
</form>
```

---

### Task 6: Wire shell layouts

**Files:**
- Modify: `resources/views/layouts/partials/sidebar.blade.php`
- Modify: `resources/views/layouts/partials/topbar.blade.php`
- Modify: `resources/views/layouts/app.blade.php`
- Modify: `resources/views/layouts/guest.blade.php`

- [ ] **Step 1: Update sidebar — replace hardcoded nav strings**

Examples (apply to all items):

```blade
aria-label="{{ __('ui.nav.main') }}"
```

```blade
{{ __('ui.nav.sections.operation') }}
{{ __('ui.nav.dashboard') }}
{{ __('ui.nav.cobranza') }}
```

Map each nav label and aria-label to the matching `ui.nav.*` key from Task 4.

- [ ] **Step 2: Update topbar**

Add before plaza selector block:

```blade
<x-ui.locale-switcher />
```

Replace strings:

```blade
aria-label="{{ __('ui.nav.open_menu') }}"
{{ __('ui.topbar.search') }}
aria-label="{{ __('ui.topbar.search_aria') }}"
{{ __('ui.topbar.plaza') }}
<option value="">{{ __('ui.topbar.all_plazas') }}</option>
{{ __('ui.nav.new_contract') }}
{{ __('ui.topbar.logout') }}
```

- [ ] **Step 3: Update app layout**

```blade
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
```

Toast:

```blade
{{ __('ui.toasts.expense_created') }}
```

- [ ] **Step 4: Update guest layout**

Wrap body content; add switcher:

```blade
<div class="relative min-h-screen ...">
    <div class="absolute right-4 top-4 z-10 sm:right-6 sm:top-6">
        <x-ui.locale-switcher />
    </div>
    ...
```

Marketing panel:

```blade
{{ __('ui.guest.badge') }}
{{ __('ui.guest.headline') }}
{{ __('ui.guest.description') }}
@foreach (__('ui.guest.bullets') as $bullet)
```

- [ ] **Step 5: Add shell i18n test**

Append to `LocaleSwitcherTest.php`:

```php
public function test_sidebar_renders_english_when_locale_is_en(): void
{
    $user = User::factory()->create(['locale' => 'en']);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Collections', false);
    $response->assertSee('Sign out', false);
    $response->assertSee('lang="en"', false);
}
```

Run: `./vendor/bin/sail test --filter=LocaleSwitcherTest`  
Expected: PASS (7 tests)

---

### Task 7: Wire auth views and controllers

**Files:**
- Modify: all `resources/views/auth/*.blade.php`
- Modify: `routes/web.php` (login error, verification messages)
- Modify: `app/Http/Controllers/Auth/PasswordResetController.php`
- Modify: `app/Http/Controllers/Auth/RegisterController.php`

- [ ] **Step 1: Update login.blade.php**

Replace title extend, labels, buttons with `__('auth.*')` keys. Update inline JS strings:

```javascript
toggleButton.setAttribute('aria-label', showing ? @json(__('auth.login.show_password')) : @json(__('auth.login.hide_password')));
submitLabel.textContent = @json(__('auth.login.submitting'));
```

- [ ] **Step 2: Update register, forgot-password, reset-password, verify-email**

Same pattern: `__('auth.{view}.*')` for all visible strings. Register invitation flow:

```blade
{{ $isInvitationFlow ? __('auth.register.badge_invite') : __('auth.register.badge') }}
```

Verify email status block — keep machine key in session, translate display:

```blade
@if (session('status') === 'verification-link-sent')
    ...
    {{ __('auth.verify_email.link_sent') }}
@endif
```

- [ ] **Step 3: Update routes/web.php flash messages**

```php
'email' => __('messages.invalid_credentials'),
```

```php
return redirect()->route('dashboard')->with('success', __('messages.email_verified'));
```

- [ ] **Step 4: Update PasswordResetController**

Replace validation messages and flash strings with `__('messages.validation.*')` and `__('messages.*')`.

- [ ] **Step 5: Update RegisterController**

Replace validation messages with `__('messages.validation.*')` and invite errors with `__('messages.validation.invite_*')`.

- [ ] **Step 6: Add login i18n test**

```php
public function test_login_page_renders_english_when_session_locale_is_en(): void
{
    $response = $this->withSession(['locale' => 'en'])->get(route('login'));

    $response->assertOk();
    $response->assertSee('Sign in', false);
    $response->assertSee('Forgot your password?', false);
}
```

Run: `./vendor/bin/sail test --filter=LocaleSwitcherTest`  
Expected: PASS (8 tests)

---

### Task 8: Final verification

- [ ] **Step 1: Run full locale test suite**

Run: `./vendor/bin/sail test --filter=LocaleSwitcherTest`  
Expected: PASS (8 tests)

- [ ] **Step 2: Run Pint**

Run: `./vendor/bin/sail pint --dirty`  
Expected: no formatting issues

- [ ] **Step 3: Run layout regression test**

Run: `./vendor/bin/sail test --filter=AppShellRedesignTest`  
Expected: PASS (sidebar/topbar still render; `Buscar` still visible in default `es` locale)

- [ ] **Step 4: Manual smoke**

1. Open `/login` → switch EN → labels in English
2. Login → topbar shows EN, sidebar says "Collections"
3. Refresh → still EN
4. Switch ES → back to Spanish
5. Logout → guest switcher retains last session locale

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| SetLocale middleware | Task 2 |
| users.locale migration | Task 1 |
| POST /locale route | Task 3 |
| locale-switcher component | Task 5 |
| Topbar + guest placement | Task 6 |
| lang/es + lang/en files | Task 4 |
| Sidebar translation | Task 6 |
| Auth views translation | Task 7 |
| Flash messages in routes/controllers | Task 7 |
| Default locale `es` | Task 1 |
| Tests per spec | Tasks 2, 3, 6, 7, 8 |
| Out of scope (Livewire modules) | Not touched |
