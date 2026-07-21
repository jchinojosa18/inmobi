# Property Code Sync Units Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When a building’s `code` is edited in Properties, rewrite unit codes that use the `{oldCode}-{number}` prefix to the new prefix; block clearing the building code if such units exist.

**Architecture:** Add `propertyHasPrefixedUnits` and `syncUnitCodesAfterPropertyCodeChange` on `UnitNumberingService`. `Properties\Index::save()` validates “cannot clear code with prefixed units”, then in a DB transaction updates the property and syncs matching unit codes. No schema, observer, or new Action.

**Tech Stack:** Laravel 11, Livewire 4, PHPUnit via Sail, Pint.

## Global Constraints

- Run all commands via `./vendor/bin/sail` (never bare `php artisan` / `phpunit` / `pint`).
- Diff mínimo: no refactor fuera del alcance; no tocar casas/locales standalone.
- Solo reescribir unidades cuyo `code` empieza exactamente con `{oldCode}-`.
- No modificar `units.name` ni otros campos de unidad.
- Spec: `docs/superpowers/specs/2026-07-21-property-code-sync-units-design.md`.
- Tests: `./vendor/bin/sail test --filter=PropertyCodeSyncUnits`; format: `./vendor/bin/sail pint --dirty`.

---

## File Map

| File | Responsibility |
|------|----------------|
| `app/Support/UnitNumberingService.php` | `propertyHasPrefixedUnits`, `syncUnitCodesAfterPropertyCodeChange` |
| `app/Livewire/Properties/Index.php` | Validate clear-code; transaction + sync on code change |
| `lang/es/catalog.php` | `validation.property_code_required_with_units` |
| `lang/en/catalog.php` | Same key in English |
| `tests/Feature/Properties/PropertyCodeSyncUnitsTest.php` | Livewire coverage for sync / block / no-op cases |

---

### Task 1: Failing feature tests

**Files:**
- Create: `tests/Feature/Properties/PropertyCodeSyncUnitsTest.php`

**Interfaces:**
- Consumes: `App\Livewire\Properties\Index`, `Property`/`Unit`/`User` factories, Admin role via `TestCase`
- Produces: failing tests that define expected edit behavior

- [ ] **Step 1: Write the failing test file**

Create `tests/Feature/Properties/PropertyCodeSyncUnitsTest.php`:

```php
<?php

namespace Tests\Feature\Properties;

use App\Livewire\Properties\Index;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PropertyCodeSyncUnitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_property_code_rewrites_prefixed_unit_codes_only(): void
    {
        [$user, $property] = $this->makeBuildingWithUnits('EDIF-A', [
            'EDIF-A-101',
            'PENTHOUSE',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('startEdit', $property->id)
            ->set('code', 'TORRE-B')
            ->call('save')
            ->assertHasNoErrors();

        $property->refresh();
        $this->assertSame('TORRE-B', $property->code);

        $codes = Unit::query()
            ->where('property_id', $property->id)
            ->orderBy('code')
            ->pluck('code')
            ->all();

        $this->assertSame(['PENTHOUSE', 'TORRE-B-101'], $codes);
    }

    public function test_clearing_property_code_is_blocked_when_prefixed_units_exist(): void
    {
        [$user, $property] = $this->makeBuildingWithUnits('EDIF-A', [
            'EDIF-A-101',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('startEdit', $property->id)
            ->set('code', '')
            ->call('save')
            ->assertHasErrors(['code']);

        $property->refresh();
        $this->assertSame('EDIF-A', $property->code);
        $this->assertSame(
            'EDIF-A-101',
            Unit::query()->where('property_id', $property->id)->value('code')
        );
    }

    public function test_updating_other_fields_without_code_change_does_not_touch_units(): void
    {
        [$user, $property] = $this->makeBuildingWithUnits('EDIF-A', [
            'EDIF-A-101',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('startEdit', $property->id)
            ->set('name', 'Edificio Renombrado')
            ->call('save')
            ->assertHasNoErrors();

        $property->refresh();
        $this->assertSame('EDIFICIO RENOMBRADO', $property->name);
        $this->assertSame('EDIF-A', $property->code);
        $this->assertSame(
            'EDIF-A-101',
            Unit::query()->where('property_id', $property->id)->value('code')
        );
    }

    public function test_clearing_property_code_is_allowed_without_prefixed_units(): void
    {
        [$user, $property] = $this->makeBuildingWithUnits('EDIF-A', [
            'PENTHOUSE',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('startEdit', $property->id)
            ->set('code', '')
            ->call('save')
            ->assertHasNoErrors();

        $property->refresh();
        $this->assertNull($property->code);
        $this->assertSame(
            'PENTHOUSE',
            Unit::query()->where('property_id', $property->id)->value('code')
        );
    }

    /**
     * @param  list<string>  $unitCodes
     * @return array{0: User, 1: Property}
     */
    private function makeBuildingWithUnits(string $propertyCode, array $unitCodes): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => $propertyCode,
            'kind' => Property::KIND_BUILDING,
        ]);

        foreach ($unitCodes as $code) {
            Unit::factory()->create([
                'organization_id' => $organization->id,
                'property_id' => $property->id,
                'code' => $code,
                'name' => 'Departamento '.$code,
            ]);
        }

        return [$user, $property];
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/sail test --filter=PropertyCodeSyncUnits`

Expected: FAIL — sync/block behavior not implemented yet (assertions on rewritten codes / `assertHasErrors(['code'])` fail).

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Properties/PropertyCodeSyncUnitsTest.php
git commit -m "$(cat <<'EOF'
Add failing tests for property code unit sync.

EOF
)"
```

---

### Task 2: UnitNumberingService helpers

**Files:**
- Modify: `app/Support/UnitNumberingService.php`

**Interfaces:**
- Consumes: existing `extractUnitNumber`, `buildUnitCode`, `Unit` model
- Produces:
  - `propertyHasPrefixedUnits(Property $property, string $propertyCode): bool`
  - `syncUnitCodesAfterPropertyCodeChange(Property $property, ?string $oldCode, ?string $newCode): int`

- [ ] **Step 1: Add `propertyHasPrefixedUnits`**

Append to `UnitNumberingService` (public methods near `buildUnitCode`):

```php
public function propertyHasPrefixedUnits(Property $property, string $propertyCode): bool
{
    $propertyCode = trim($propertyCode);
    if ($propertyCode === '') {
        return false;
    }

    $prefix = $propertyCode.'-';

    return Unit::query()
        ->where('property_id', $property->id)
        ->whereNotNull('code')
        ->get(['code'])
        ->contains(fn (Unit $unit): bool => str_starts_with((string) $unit->code, $prefix));
}
```

- [ ] **Step 2: Add `syncUnitCodesAfterPropertyCodeChange`**

```php
public function syncUnitCodesAfterPropertyCodeChange(
    Property $property,
    ?string $oldCode,
    ?string $newCode,
): int {
    $oldCode = $oldCode !== null ? trim($oldCode) : '';
    $newCode = $newCode !== null ? trim($newCode) : '';

    if ($oldCode === '' || $newCode === '' || $oldCode === $newCode) {
        return 0;
    }

    $prefix = $oldCode.'-';
    $updated = 0;

    $units = Unit::query()
        ->where('property_id', $property->id)
        ->whereNotNull('code')
        ->get(['id', 'code']);

    foreach ($units as $unit) {
        $code = (string) $unit->code;
        if (! str_starts_with($code, $prefix)) {
            continue;
        }

        $number = $this->extractUnitNumber($oldCode, $code);
        if ($number === null || $number === '') {
            continue;
        }

        Unit::query()
            ->whereKey($unit->id)
            ->update([
                'code' => $this->buildUnitCode($property, $number),
            ]);

        $updated++;
    }

    return $updated;
}
```

Note: caller must refresh/update `$property->code` to `$newCode` **before** calling sync so `buildUnitCode` uses the new prefix. Do not wrap sync in its own transaction; Livewire owns the transaction.

- [ ] **Step 3: Commit**

```bash
git add app/Support/UnitNumberingService.php
git commit -m "$(cat <<'EOF'
Add unit code prefix sync helpers on UnitNumberingService.

EOF
)"
```

---

### Task 3: Wire Properties Index + i18n

**Files:**
- Modify: `app/Livewire/Properties/Index.php`
- Modify: `lang/es/catalog.php`
- Modify: `lang/en/catalog.php`

**Interfaces:**
- Consumes: `UnitNumberingService::propertyHasPrefixedUnits`, `UnitNumberingService::syncUnitCodesAfterPropertyCodeChange`
- Produces: `save()` with clear-code guard + transactional sync

- [ ] **Step 1: Add i18n keys**

In `lang/es/catalog.php` inside `validation` (next to `property_code_required`):

```php
'property_code_required_with_units' => 'No puedes quitar el código del edificio mientras haya unidades con ese prefijo.',
```

In `lang/en/catalog.php`:

```php
'property_code_required_with_units' => 'You cannot clear the building code while units still use that prefix.',
```

- [ ] **Step 2: Update `save()` in `Properties\Index`**

Add imports:

```php
use App\Support\UnitNumberingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
```

Replace the body of `save()` after building `$payload` / `editingId` check with:

```php
public function save(): void
{
    if (! (auth()->user()?->can('properties.manage') ?? false)) {
        abort(403);
    }

    $this->normalizePropertyUppercaseFields();
    $validated = $this->validate($this->rules(), $this->messages());

    $payload = [
        'organization_id' => auth()->user()?->organization_id,
        'plaza_id' => $this->resolvePlazaIdForSave($validated['plazaId'] ?? null),
        'name' => $validated['name'],
        'code' => $validated['code'] ?: null,
        'status' => $validated['formStatus'],
        'address' => $validated['address'] ?: null,
        'notes' => $validated['notes'] ?: null,
    ];

    if ($this->editingId === null) {
        return;
    }

    $property = Property::query()->findOrFail($this->editingId);
    $oldCode = $property->code !== null ? trim((string) $property->code) : '';
    $newCode = $payload['code'] !== null ? trim((string) $payload['code']) : '';
    $numberingService = app(UnitNumberingService::class);

    if ($newCode === '' && $oldCode !== '' && $numberingService->propertyHasPrefixedUnits($property, $oldCode)) {
        throw ValidationException::withMessages([
            'code' => __('catalog.validation.property_code_required_with_units'),
        ]);
    }

    DB::transaction(function () use ($property, $payload, $oldCode, $newCode, $numberingService): void {
        $property->update($payload);

        if ($oldCode !== '' && $newCode !== '' && $oldCode !== $newCode) {
            $numberingService->syncUnitCodesAfterPropertyCodeChange(
                $property->fresh(),
                $oldCode,
                $newCode,
            );
        }
    });

    session()->flash('success', __('catalog.flash.property_updated'));

    $this->resetForm();
    $this->resetPage();
}
```

Keep the rest of the class unchanged.

- [ ] **Step 3: Run feature tests**

Run: `./vendor/bin/sail test --filter=PropertyCodeSyncUnits`

Expected: PASS (4 tests).

- [ ] **Step 4: Pint**

Run: `./vendor/bin/sail pint --dirty`

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/Properties/Index.php lang/es/catalog.php lang/en/catalog.php
git commit -m "$(cat <<'EOF'
Sync prefixed unit codes when a building code changes.

EOF
)"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Rewrite only `{old}-*` units | Task 2 + 3 |
| Leave custom codes alone | Task 1 test + Task 2 filter |
| Block clearing code with prefixed units | Task 1 + 3 |
| Other-field edit does not touch units | Task 1 + 3 (`old === new` skip) |
| Allow clear without prefixed units | Task 1 + 3 |
| No name updates / no observer / no Action | Out of scope honored |
| i18n message | Task 3 |

## Self-review

- No TBD/placeholder steps.
- Method signatures consistent across tasks.
- Sail commands only.
- TDD: failing tests first, then service, then Livewire wiring.
