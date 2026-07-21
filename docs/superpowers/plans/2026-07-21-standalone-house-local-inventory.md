# Standalone House/Local Inventory Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show and operate the existing master inventory on house and local detail pages (`houses.show`), reusing the internal `Unit` and `Units\InventoryPanel`.

**Architecture:** No schema changes. `Houses\Show` already loads the single standalone `Unit`. Embed `InventoryPanel` in `houses/show.blade.php` when the property kind is house or local and the user has `units.view`. Land stays without inventory UI.

**Tech Stack:** Laravel 11, Livewire 4, Spatie Permission, Sail for tests/pint.

## Global Constraints

- Run all commands via `./vendor/bin/sail` (never bare `php artisan`).
- No migrations, no new permissions, no new routes.
- Inventory remains on `unit_inventory_items` via the standalone `Unit`.
- Land (`Property::KIND_LAND`) must not render `InventoryPanel`.
- Panel permissions stay `units.view` / `units.manage` / `documents.*` (existing `InventoryPanel` logic).
- Wrap panel with `@can('units.view')` so `properties.view`-only users still load the house/local page.
- Tests: `./vendor/bin/sail test --filter=StandaloneHouseInventory`; format: `./vendor/bin/sail pint --dirty`.
- Spec: `docs/superpowers/specs/2026-07-21-standalone-house-local-inventory-design.md`.

---

## File Map

| File | Responsibility |
|------|----------------|
| `tests/Feature/Houses/StandaloneHouseInventoryTest.php` | Feature coverage for house/local/land + permission gate |
| `resources/views/livewire/houses/show.blade.php` | Embed inventory panel under existing stats |
| `lang/es/inventory.php` | Neutralize “departamento” copy shared with house/local |
| `lang/en/inventory.php` | Keep EN aligned if ES wording changes require it (EN already says “unit”) |

No changes to `Houses\Show.php`, models, routes, or `InventoryPanel` unless a test proves otherwise.

---

### Task 1: Failing feature tests for house / local / land / permission gate

**Files:**
- Create: `tests/Feature/Houses/StandaloneHouseInventoryTest.php`

**Interfaces:**
- Consumes: `route('houses.show')`, `Property::factory()->standaloneHouse|standaloneLocal|standaloneLand()`, `Unit::factory()->house|local|land()`, `App\Livewire\Units\InventoryPanel`
- Produces: failing tests that define expected show-page behavior

- [ ] **Step 1: Write the failing test file**

Create `tests/Feature/Houses/StandaloneHouseInventoryTest.php`:

```php
<?php

namespace Tests\Feature\Houses;

use App\Livewire\Units\InventoryPanel;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StandaloneHouseInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_house_show_includes_inventory_panel(): void
    {
        [$user, $property] = $this->makeStandaloneWithUnit(
            Property::KIND_STANDALONE_HOUSE,
            Unit::KIND_HOUSE,
        );

        $this->actingAs($user)
            ->get(route('houses.show', $property))
            ->assertOk()
            ->assertSeeLivewire(InventoryPanel::class)
            ->assertSee(__('inventory.title'));
    }

    public function test_local_show_includes_inventory_panel(): void
    {
        [$user, $property] = $this->makeStandaloneWithUnit(
            Property::KIND_LOCAL,
            Unit::KIND_LOCAL,
        );

        $this->actingAs($user)
            ->get(route('houses.show', $property))
            ->assertOk()
            ->assertSeeLivewire(InventoryPanel::class)
            ->assertSee(__('inventory.title'));
    }

    public function test_land_show_does_not_include_inventory_panel(): void
    {
        [$user, $property] = $this->makeStandaloneWithUnit(
            Property::KIND_LAND,
            Unit::KIND_LAND,
        );

        $this->actingAs($user)
            ->get(route('houses.show', $property))
            ->assertOk()
            ->assertDontSeeLivewire(InventoryPanel::class)
            ->assertDontSee(__('inventory.title'));
    }

    public function test_house_show_without_units_view_loads_without_inventory_panel(): void
    {
        $organization = Organization::factory()->create();
        $role = Role::findOrCreate('SoloPropiedades', 'web');
        $role->syncPermissions(['properties.view']);
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $user->syncRoles([$role]);

        $property = Property::factory()->standaloneHouse()->create([
            'organization_id' => $organization->id,
        ]);
        Unit::factory()->house()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'name' => 'Casa',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('houses.show', $property))
            ->assertOk()
            ->assertDontSeeLivewire(InventoryPanel::class);
    }

    /**
     * @return array{0: User, 1: Property}
     */
    private function makeStandaloneWithUnit(string $propertyKind, string $unitKind): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);

        $propertyFactory = match ($propertyKind) {
            Property::KIND_STANDALONE_HOUSE => Property::factory()->standaloneHouse(),
            Property::KIND_LOCAL => Property::factory()->standaloneLocal(),
            Property::KIND_LAND => Property::factory()->standaloneLand(),
            default => throw new \InvalidArgumentException($propertyKind),
        };

        $property = $propertyFactory->create([
            'organization_id' => $organization->id,
        ]);

        $unitFactory = match ($unitKind) {
            Unit::KIND_HOUSE => Unit::factory()->house(),
            Unit::KIND_LOCAL => Unit::factory()->local(),
            Unit::KIND_LAND => Unit::factory()->land(),
            default => throw new \InvalidArgumentException($unitKind),
        };

        $unitFactory->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'name' => match ($unitKind) {
                Unit::KIND_HOUSE => 'Casa',
                Unit::KIND_LOCAL => 'Local',
                Unit::KIND_LAND => 'Terreno',
                default => 'Unit',
            },
            'status' => 'active',
        ]);

        return [$user, $property];
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/sail test --filter=StandaloneHouseInventory
```

Expected: house/local tests FAIL (no Livewire panel / no `inventory.title` on `houses.show`). Land and no-`units.view` may already PASS (panel absent).

- [ ] **Step 3: Commit tests only**

```bash
git add tests/Feature/Houses/StandaloneHouseInventoryTest.php
git commit -m "$(cat <<'EOF'
Add failing tests for house/local master inventory surface.

EOF
)"
```

---

### Task 2: Embed InventoryPanel on house/local show

**Files:**
- Modify: `resources/views/livewire/houses/show.blade.php`
- Test: `tests/Feature/Houses/StandaloneHouseInventoryTest.php`

**Interfaces:**
- Consumes: `$property`, `$unit` already passed from `Houses\Show::render()`
- Produces: inventory UI on casa/local when `@can('units.view')`

- [ ] **Step 1: Update the Blade view**

Replace `resources/views/livewire/houses/show.blade.php` with:

```blade
<section class="space-y-6">
    <x-ui.page-header
        :title="$property->name"
        :description="__('catalog.houses.description', ['type' => $entityLabel])"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('properties.index') }}" variant="secondary">
                {{ __('common.back_to_properties') }}
            </x-ui.button>
            @if ($canManageContracts)
                <x-ui.button href="{{ route('contracts.index', ['create_contract' => 1, 'unit_id' => $unit->id]) }}">
                    {{ __('common.new_contract') }}
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <x-ui.stat-card
                :label="__('common.property')"
                :value="$property->name"
                :hint="$property->address ?: __('common.no_address')"
            />
            @if ($property->notes)
                <p class="mt-3 text-sm text-slate-600">{{ $property->notes }}</p>
            @endif
        </div>

        <x-ui.stat-card
            :label="__('catalog.houses.single_unit')"
            :value="$unit->name"
            :hint="__('catalog.houses.type_code_hint', ['kind' => $unit->kind, 'code' => $unit->code ?: __('common.no_code')])"
        />
    </div>

    @if (in_array($property->kind, [\App\Models\Property::KIND_STANDALONE_HOUSE, \App\Models\Property::KIND_LOCAL], true))
        @can('units.view')
            <livewire:units.inventory-panel :unit="$unit" :key="'inventory-panel-'.$unit->id" />
        @endcan
    @endif
</section>
```

Prefer importing kinds via already-available `$property->kind` constants as shown (FQCN matches existing Blade style elsewhere if present). Do not change `Houses\Show.php`.

- [ ] **Step 2: Run tests to verify they pass**

```bash
./vendor/bin/sail test --filter=StandaloneHouseInventory
```

Expected: all 4 tests PASS.

- [ ] **Step 3: Commit**

```bash
git add resources/views/livewire/houses/show.blade.php
git commit -m "$(cat <<'EOF'
Show master inventory on house and local detail pages.

EOF
)"
```

---

### Task 3: Neutralize Spanish copy that says “departamento”

**Files:**
- Modify: `lang/es/inventory.php`
- Modify: `lang/en/inventory.php` only if needed for parity (EN already uses “unit”)

**Interfaces:**
- Consumes: existing keys `section_description`, `empty_cta`
- Produces: copy valid for apartment, house, and local

- [ ] **Step 1: Update Spanish strings**

In `lang/es/inventory.php`, change:

```php
'section_description' => 'Registro de mobiliario y equipo de la unidad con evidencia fotográfica.',
'empty_cta' => 'Agrega el primer ítem para documentar la unidad.',
```

Leave EN as-is unless a key is missing (it already says “unit”).

- [ ] **Step 2: Smoke the house show copy**

```bash
./vendor/bin/sail test --filter=StandaloneHouseInventory
```

Expected: PASS (assertions use `inventory.title`, not the changed strings).

- [ ] **Step 3: Format and commit**

```bash
./vendor/bin/sail pint --dirty
git add lang/es/inventory.php
git commit -m "$(cat <<'EOF'
Neutralize inventory copy for house and local surfaces.

EOF
)"
```

---

### Task 4: Final verification

**Files:** none new

- [ ] **Step 1: Run related inventory + house filters**

```bash
./vendor/bin/sail test --filter='StandaloneHouseInventory|UnitInventoryShow|UnitInventoryPanel'
./vendor/bin/sail pint --dirty
```

Expected: all green; Pint reports no changes (or only auto-fixes already committed).

- [ ] **Step 2: Mark spec status**

In `docs/superpowers/specs/2026-07-21-standalone-house-local-inventory-design.md`, set:

```markdown
**Status:** Implemented
```

- [ ] **Step 3: Commit docs**

```bash
git add docs/superpowers/specs/2026-07-21-standalone-house-local-inventory-design.md docs/superpowers/specs/2026-07-16-unit-inventory-design.md docs/superpowers/plans/2026-07-21-standalone-house-local-inventory.md
git commit -m "$(cat <<'EOF'
Document house/local inventory design and implementation plan.

EOF
)"
```

(Include the plan file and any pending design-spec files that are still untracked/modified.)

---

## Spec Coverage Checklist

| Spec requirement | Task |
|------------------|------|
| Casa muestra inventario | Task 1 + 2 |
| Local muestra inventario | Task 1 + 2 |
| Terreno sin panel | Task 1 + 2 |
| Sin `units.view` ficha carga sin panel | Task 1 + 2 |
| Reutilizar `Unit` + `InventoryPanel` | Task 2 |
| Sin migraciones / permisos / rutas nuevas | All tasks |
| Copy usable en casa/local | Task 3 |
| CRUD/fotos no duplicados | Covered by existing `UnitInventoryPanelTest` (Task 4 regression) |
