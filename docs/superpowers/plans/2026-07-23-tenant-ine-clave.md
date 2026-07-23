# Tenant INE Clave de Elector Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an optional, org-unique INE clave de elector (`ine_clave`) on tenants, editable from index/kardex forms, and visible on the kardex profile.

**Architecture:** New nullable `tenants.ine_clave` column with unique `(organization_id, ine_clave)`. Livewire `Tenants\Index` and `Tenants\Show` normalize (trim/uppercase/empty→null), validate format + uniqueness, and persist. Kardex profile shows the value or `common.n_a`.

**Tech Stack:** Laravel 11, Livewire 4, MySQL (Sail), PHPUnit feature tests, Pint.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-23-tenant-ine-clave-design.md`
- Run all commands via `./vendor/bin/sail` (never bare `php artisan` / `phpunit`)
- Column: `ine_clave` string(18) nullable; unique index `(organization_id, ine_clave)`; multiple NULLs OK
- Normalization before validate: trim → uppercase → `''` → `null`
- When present: `/^[A-Z0-9]{18}$/` and unique per organization (DB + `Rule::unique`; soft-deleted rows still occupy the clave)
- No list column, no search by INE, no required field, no checksum validation
- i18n in `lang/es/catalog.php` and `lang/en/catalog.php`
- Tests: `./vendor/bin/sail test --filter=...`; format: `./vendor/bin/sail pint --dirty`

---

## File Map

| File | Responsibility |
|------|----------------|
| `database/migrations/2026_07_23_xxxxxx_add_ine_clave_to_tenants_table.php` | Add column + unique index |
| `app/Models/Tenant.php` | fillable + auditable |
| `database/factories/TenantFactory.php` | default `ine_clave` => null |
| `app/Livewire/Tenants/Index.php` | create/edit save + validation |
| `app/Livewire/Tenants/Show.php` | kardex edit save + validation |
| `resources/views/livewire/tenants/index.blade.php` | form field |
| `resources/views/livewire/tenants/show.blade.php` | form field + profile row |
| `lang/es/catalog.php`, `lang/en/catalog.php` | label + validation messages |
| `tests/Feature/Tenants/TenantIneClaveTest.php` | create/edit/unique/display coverage |

---

### Task 1: Schema + model + factory

**Files:**
- Create: `database/migrations/2026_07_23_120000_add_ine_clave_to_tenants_table.php`
- Modify: `app/Models/Tenant.php`
- Modify: `database/factories/TenantFactory.php`
- Test: `tests/Feature/Tenants/TenantIneClaveTest.php` (first assertion only)

**Interfaces:**
- Produces: `tenants.ine_clave` nullable string(18); model attribute `ine_clave`; factory defaults to `null`

- [ ] **Step 1: Write the failing persistence test**

Create `tests/Feature/Tenants/TenantIneClaveTest.php`:

```php
<?php

namespace Tests\Feature\Tenants;

use App\Models\Organization;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIneClaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_can_persist_optional_ine_clave(): void
    {
        $organization = Organization::factory()->create();

        $tenant = Tenant::query()->create([
            'organization_id' => $organization->id,
            'full_name' => 'Ana Pérez',
            'status' => 'active',
            'ine_clave' => 'ABCD120101HDFRRN09',
        ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'ine_clave' => 'ABCD120101HDFRRN09',
        ]);

        $without = Tenant::query()->create([
            'organization_id' => $organization->id,
            'full_name' => 'Luis Gómez',
            'status' => 'active',
            'ine_clave' => null,
        ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $without->id,
            'ine_clave' => null,
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail test --filter=test_tenant_can_persist_optional_ine_clave`

Expected: FAIL (unknown column / mass assignment)

- [ ] **Step 3: Add migration**

Create `database/migrations/2026_07_23_120000_add_ine_clave_to_tenants_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('ine_clave', 18)->nullable()->after('phone');
            $table->unique(['organization_id', 'ine_clave']);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropUnique(['organization_id', 'ine_clave']);
            $table->dropColumn('ine_clave');
        });
    }
};
```

- [ ] **Step 4: Update model and factory**

In `app/Models/Tenant.php` `$fillable`, add `'ine_clave'` after `'phone'`.

In `auditableAttributes()`, add `'ine_clave'` after `'phone'`.

In `database/factories/TenantFactory.php` `definition()`, add:

```php
'ine_clave' => null,
```

- [ ] **Step 5: Migrate and run test**

Run:

```bash
./vendor/bin/sail artisan migrate
./vendor/bin/sail test --filter=test_tenant_can_persist_optional_ine_clave
```

Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_23_120000_add_ine_clave_to_tenants_table.php \
  app/Models/Tenant.php database/factories/TenantFactory.php \
  tests/Feature/Tenants/TenantIneClaveTest.php
git commit -m "$(cat <<'EOF'
Add optional ine_clave column on tenants.

EOF
)"
```

---

### Task 2: i18n strings

**Files:**
- Modify: `lang/es/catalog.php`
- Modify: `lang/en/catalog.php`

**Interfaces:**
- Produces keys used by later tasks:
  - `__('catalog.tenants.ine_clave')`
  - `__('catalog.validation.ine_clave_format')`
  - `__('catalog.validation.ine_clave_unique')`

- [ ] **Step 1: Add Spanish strings**

In `lang/es/catalog.php` under `'tenants' => [...]`, add:

```php
'ine_clave' => 'Clave de elector (INE)',
```

Under `'validation' => [...]`, add:

```php
'ine_clave_format' => 'La clave de elector debe tener exactamente 18 caracteres alfanuméricos.',
'ine_clave_unique' => 'Esta clave de elector ya está registrada en la organización.',
```

- [ ] **Step 2: Add English strings**

In `lang/en/catalog.php` under `'tenants' => [...]`, add:

```php
'ine_clave' => 'Voter ID key (INE)',
```

Under `'validation' => [...]`, add:

```php
'ine_clave_format' => 'The voter ID key must be exactly 18 alphanumeric characters.',
'ine_clave_unique' => 'This voter ID key is already registered in the organization.',
```

- [ ] **Step 3: Commit**

```bash
git add lang/es/catalog.php lang/en/catalog.php
git commit -m "$(cat <<'EOF'
Add catalog i18n for tenant INE clave de elector.

EOF
)"
```

---

### Task 3: `Tenants\Index` create/edit with validation

**Files:**
- Modify: `app/Livewire/Tenants/Index.php`
- Modify: `resources/views/livewire/tenants/index.blade.php`
- Modify: `tests/Feature/Tenants/TenantIneClaveTest.php`

**Interfaces:**
- Consumes: `ine_clave` column; catalog i18n keys from Task 2
- Produces: Index modal property `public ?string $ine_clave = null`; save normalizes + validates + persists

- [ ] **Step 1: Write failing Livewire tests**

Append to `tests/Feature/Tenants/TenantIneClaveTest.php`:

```php
use App\Livewire\Tenants\Index;
use App\Models\User;
use Livewire\Livewire;

public function test_index_creates_tenant_without_ine_clave(): void
{
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('startCreate')
        ->set('full_name', 'Sin Ine')
        ->set('formStatus', 'active')
        ->set('ine_clave', '')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('tenants', [
        'organization_id' => $organization->id,
        'full_name' => 'Sin Ine',
        'ine_clave' => null,
    ]);
}

public function test_index_creates_tenant_with_normalized_ine_clave(): void
{
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('startCreate')
        ->set('full_name', 'Con Ine')
        ->set('formStatus', 'active')
        ->set('ine_clave', ' abcd120101hdfrrn09 ')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('tenants', [
        'organization_id' => $organization->id,
        'full_name' => 'Con Ine',
        'ine_clave' => 'ABCD120101HDFRRN09',
    ]);
}

public function test_index_rejects_invalid_ine_clave_format(): void
{
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('startCreate')
        ->set('full_name', 'Bad Ine')
        ->set('formStatus', 'active')
        ->set('ine_clave', 'TOO-SHORT')
        ->call('save')
        ->assertHasErrors(['ine_clave']);
}

public function test_index_rejects_duplicate_ine_clave_in_same_organization(): void
{
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    Tenant::factory()->create([
        'organization_id' => $organization->id,
        'ine_clave' => 'ABCD120101HDFRRN09',
    ]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('startCreate')
        ->set('full_name', 'Dup Ine')
        ->set('formStatus', 'active')
        ->set('ine_clave', 'ABCD120101HDFRRN09')
        ->call('save')
        ->assertHasErrors(['ine_clave']);
}

public function test_index_allows_same_ine_clave_in_different_organization(): void
{
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    Tenant::factory()->create([
        'organization_id' => $orgA->id,
        'ine_clave' => 'ABCD120101HDFRRN09',
    ]);
    $adminB = User::factory()->create(['organization_id' => $orgB->id]);

    Livewire::actingAs($adminB)
        ->test(Index::class)
        ->call('startCreate')
        ->set('full_name', 'Other Org')
        ->set('formStatus', 'active')
        ->set('ine_clave', 'ABCD120101HDFRRN09')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('tenants', [
        'organization_id' => $orgB->id,
        'full_name' => 'Other Org',
        'ine_clave' => 'ABCD120101HDFRRN09',
    ]);
}

public function test_index_edit_can_clear_ine_clave(): void
{
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $tenant = Tenant::factory()->create([
        'organization_id' => $organization->id,
        'ine_clave' => 'ABCD120101HDFRRN09',
    ]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('startEdit', $tenant->id)
        ->set('ine_clave', '')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('tenants', [
        'id' => $tenant->id,
        'ine_clave' => null,
    ]);
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/sail test --filter=TenantIneClaveTest`

Expected: FAIL on Index methods (property/validation missing)

- [ ] **Step 3: Implement Index component logic**

In `app/Livewire/Tenants/Index.php`:

1. Add property after `$phone`:

```php
public ?string $ine_clave = null;
```

2. In `startEdit`, after phone assignment:

```php
$this->ine_clave = $tenant->ine_clave;
```

3. At the start of `save()`, before `validate`:

```php
$this->ine_clave = $this->normalizeIneClave($this->ine_clave);
```

4. In `$payload` array, add:

```php
'ine_clave' => $validated['ine_clave'] ?? null,
```

5. In `rules()`, add:

```php
'ine_clave' => [
    'nullable',
    'string',
    'size:18',
    'regex:/^[A-Z0-9]{18}$/',
    Rule::unique('tenants', 'ine_clave')
        ->where(fn ($query) => $query->where('organization_id', auth()->user()?->organization_id))
        ->ignore($this->editingId),
],
```

6. In `messages()`, add:

```php
'ine_clave.size' => __('catalog.validation.ine_clave_format'),
'ine_clave.regex' => __('catalog.validation.ine_clave_format'),
'ine_clave.unique' => __('catalog.validation.ine_clave_unique'),
```

7. In `resetForm()` `$this->reset([...])`, include `'ine_clave'`.

8. Add private method:

```php
private function normalizeIneClave(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $normalized = strtoupper(trim($value));

    return $normalized === '' ? null : $normalized;
}
```

- [ ] **Step 4: Add form field in index view**

In `resources/views/livewire/tenants/index.blade.php`, inside the modal form grid, after the phone field block and before the status field, add:

```blade
<div>
    <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('catalog.tenants.ine_clave') }}</label>
    <input type="text" wire:model.blur="ine_clave" maxlength="18" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm uppercase" autocomplete="off">
    @error('ine_clave') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>
```

- [ ] **Step 5: Run tests**

Run: `./vendor/bin/sail test --filter=TenantIneClaveTest`

Expected: all Index-related tests PASS (Show tests not added yet — only the ones from Tasks 1–3)

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Tenants/Index.php \
  resources/views/livewire/tenants/index.blade.php \
  tests/Feature/Tenants/TenantIneClaveTest.php
git commit -m "$(cat <<'EOF'
Capture optional INE clave on tenant create and edit.

EOF
)"
```

---

### Task 4: Kardex profile display + Show edit modal

**Files:**
- Modify: `app/Livewire/Tenants/Show.php`
- Modify: `resources/views/livewire/tenants/show.blade.php`
- Modify: `tests/Feature/Tenants/TenantIneClaveTest.php`

**Interfaces:**
- Consumes: `ine_clave` column; same normalization/validation rules as Index
- Produces: Show property `public ?string $ine_clave = null`; profile row on kardex

- [ ] **Step 1: Write failing Show tests**

Append to `tests/Feature/Tenants/TenantIneClaveTest.php`:

```php
use App\Livewire\Tenants\Show;

public function test_kardex_shows_ine_clave_or_n_a(): void
{
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $withClave = Tenant::factory()->create([
        'organization_id' => $organization->id,
        'ine_clave' => 'ABCD120101HDFRRN09',
    ]);
    $withoutClave = Tenant::factory()->create([
        'organization_id' => $organization->id,
        'ine_clave' => null,
    ]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['tenant' => $withClave])
        ->assertSeeText('ABCD120101HDFRRN09')
        ->assertSeeText(__('catalog.tenants.ine_clave'));

    Livewire::actingAs($admin)
        ->test(Show::class, ['tenant' => $withoutClave])
        ->assertSeeText(__('common.n_a'));
}

public function test_show_edit_updates_and_clears_ine_clave(): void
{
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $tenant = Tenant::factory()->create([
        'organization_id' => $organization->id,
        'ine_clave' => null,
    ]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['tenant' => $tenant])
        ->call('startEdit')
        ->set('ine_clave', 'abcd120101hdfrrn09')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('tenants', [
        'id' => $tenant->id,
        'ine_clave' => 'ABCD120101HDFRRN09',
    ]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['tenant' => $tenant->fresh()])
        ->call('startEdit')
        ->set('ine_clave', '')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('tenants', [
        'id' => $tenant->id,
        'ine_clave' => null,
    ]);
}
```

- [ ] **Step 2: Run new tests to verify they fail**

Run: `./vendor/bin/sail test --filter=test_kardex_shows_ine_clave_or_n_a`

Expected: FAIL (label/value missing)

- [ ] **Step 3: Implement Show component logic**

In `app/Livewire/Tenants/Show.php`:

1. Add `public ?string $ine_clave = null;` after `$phone`.

2. In `startEdit`, set `$this->ine_clave = $this->tenant->ine_clave;`.

3. At start of `save()`, normalize:

```php
$this->ine_clave = $this->normalizeIneClave($this->ine_clave);
```

4. Extend validate rules/messages with the same `ine_clave` rules as Index, but:

```php
Rule::unique('tenants', 'ine_clave')
    ->where(fn ($query) => $query->where('organization_id', auth()->user()?->organization_id))
    ->ignore($this->tenant->id),
```

5. Include `'ine_clave' => $validated['ine_clave'] ?? null` in `update([...])`.

6. Add the same private `normalizeIneClave(?string $value): ?string` method as Index.

- [ ] **Step 4: Update show blade — profile + modal**

In `resources/views/livewire/tenants/show.blade.php` profile `<dl>`, after the phone `<div>` and before status, add:

```blade
<div>
    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('catalog.tenants.ine_clave') }}</dt>
    <dd class="mt-1 text-sm text-slate-900">{{ $tenant->ine_clave ?: __('common.n_a') }}</dd>
</div>
```

In the edit modal form, after the phone field and before status, add the same input block as Index:

```blade
<div>
    <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('catalog.tenants.ine_clave') }}</label>
    <input type="text" wire:model.blur="ine_clave" maxlength="18" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm uppercase" autocomplete="off">
    @error('ine_clave') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>
```

- [ ] **Step 5: Run full INE + related tenant tests**

Run:

```bash
./vendor/bin/sail test --filter='TenantIneClaveTest|TenantCreateModalTest|TenantKardexShowTest'
./vendor/bin/sail pint --dirty
```

Expected: all PASS; Pint clean

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Tenants/Show.php \
  resources/views/livewire/tenants/show.blade.php \
  tests/Feature/Tenants/TenantIneClaveTest.php
git commit -m "$(cat <<'EOF'
Show and edit tenant INE clave on kardex profile.

EOF
)"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|---|---|
| `ine_clave` column + unique index | Task 1 |
| fillable + auditable + factory | Task 1 |
| Optional create without clave | Task 3 |
| Normalize trim/uppercase | Task 3, 4 |
| Format validation 18 alnum | Task 3 |
| Unique per org | Task 3 |
| Cross-org duplicate allowed | Task 3 |
| Edit clear / update | Task 3, 4 |
| Form fields index + show | Task 3, 4 |
| Kardex profile display / N/A | Task 4 |
| i18n ES/EN | Task 2 |
| No list column / no search | Explicit non-goal (no task) |
