# Unit Master Inventory Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add master inventory per building unit with structured items (name, quantity, condition, notes) and optional photo evidence per item, on a new unit detail page.

**Architecture:** New `unit_inventory_items` table and `UnitInventoryItem` model; photos via existing `Document` morph; Livewire `Units\Show` page embeds `Units\InventoryPanel` for CRUD and uploads. Reuse `units.view`/`units.manage` and `documents.*` permissions.

**Tech Stack:** Laravel 11, Livewire 4, Tailwind, Spatie Permission, Sail for artisan/test/pint.

## Global Constraints

- Run all commands via `./vendor/bin/sail` (never bare `php artisan`).
- Multi-tenant: all models extend `OrganizationScopedModel`; validate `organization_id` on mount.
- Condition enum values: `good`, `fair`, `poor` only.
- Photo MIME: `jpg`, `jpeg`, `png` only; max 5 MB; max 5 photos per item.
- No new permissions in v1.
- No month-close guard on inventory (non-financial).
- Tests: `./vendor/bin/sail test --filter=...`; format: `./vendor/bin/sail pint --dirty`.
- i18n: add `lang/es/inventory.php` and `lang/en/inventory.php`.

---

## File Map

| File | Responsibility |
|------|----------------|
| `database/migrations/..._create_unit_inventory_items_table.php` | Schema |
| `app/Models/UnitInventoryItem.php` | Domain model |
| `database/factories/UnitInventoryItemFactory.php` | Test factory |
| `app/Models/Unit.php` | `inventoryItems()` relation |
| `app/Livewire/Units/Show.php` | Unit detail page |
| `app/Livewire/Units/InventoryPanel.php` | Inventory CRUD + photo upload |
| `resources/views/livewire/units/show.blade.php` | Page layout |
| `resources/views/livewire/units/inventory-panel.blade.php` | Inventory UI |
| `routes/web.php` | Route registration |
| `app/Livewire/Units/Index.php` | Delete guard + link |
| `resources/views/livewire/units/index.blade.php` | Link to show page |
| `lang/es/inventory.php`, `lang/en/inventory.php` | Translations |
| `tests/Feature/Units/UnitInventoryShowTest.php` | Show page tests |
| `tests/Feature/Units/UnitInventoryPanelTest.php` | Panel CRUD + photo tests |
| `tests/Feature/Units/UnitDeleteTest.php` | Extend with inventory block |

---

### Task 1: Migration, model, factory, Unit relation

**Files:**
- Create: `database/migrations/2026_07_16_000001_create_unit_inventory_items_table.php`
- Create: `app/Models/UnitInventoryItem.php`
- Create: `database/factories/UnitInventoryItemFactory.php`
- Modify: `app/Models/Unit.php`
- Test: `tests/Feature/Units/UnitInventoryModelTest.php`

**Interfaces:**
- Produces: `UnitInventoryItem` model with `CONDITION_*` constants, `inventoryItems()` on `Unit`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Units;

use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitInventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitInventoryModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_has_inventory_items_relation(): void
    {
        $organization = Organization::factory()->create();
        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $item = UnitInventoryItem::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'name' => 'Refrigerador',
            'quantity' => 1,
            'condition' => UnitInventoryItem::CONDITION_GOOD,
        ]);

        $this->assertTrue($unit->inventoryItems->contains($item));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail test --filter=UnitInventoryModelTest`
Expected: FAIL — class `UnitInventoryItem` not found

- [ ] **Step 3: Create migration**

```php
Schema::create('unit_inventory_items', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('organization_id')->constrained('organizations');
    $table->foreignId('unit_id')->constrained('units');
    $table->string('name');
    $table->unsignedInteger('quantity')->default(1);
    $table->string('condition', 16);
    $table->text('notes')->nullable();
    $table->integer('sort_order')->default(0);
    $table->timestamps();
    $table->softDeletes();

    $table->index(['organization_id', 'unit_id']);
    $table->index(['unit_id', 'sort_order']);
});
```

- [ ] **Step 4: Create model `app/Models/UnitInventoryItem.php`**

```php
<?php

namespace App\Models;

use App\Domain\Shared\OrganizationScopedModel;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitInventoryItem extends OrganizationScopedModel
{
    use Auditable, HasFactory, SoftDeletes;

    public const CONDITION_GOOD = 'good';
    public const CONDITION_FAIR = 'fair';
    public const CONDITION_POOR = 'poor';

    protected $fillable = [
        'organization_id', 'unit_id', 'name', 'quantity',
        'condition', 'notes', 'sort_order',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    protected function auditableAttributes(): array
    {
        return ['unit_id', 'name', 'quantity', 'condition', 'notes', 'sort_order'];
    }
}
```

- [ ] **Step 5: Add to `Unit.php`**

```php
public function inventoryItems(): HasMany
{
    return $this->hasMany(UnitInventoryItem::class);
}
```

- [ ] **Step 6: Create factory**

```php
public function definition(): array
{
    return [
        'organization_id' => Organization::factory(),
        'unit_id' => Unit::factory()->state(fn (array $a) => [
            'organization_id' => $a['organization_id'],
        ]),
        'name' => fake()->words(2, true),
        'quantity' => 1,
        'condition' => UnitInventoryItem::CONDITION_GOOD,
        'notes' => null,
        'sort_order' => 0,
    ];
}
```

- [ ] **Step 7: Migrate and run test**

Run: `./vendor/bin/sail artisan migrate`
Run: `./vendor/bin/sail test --filter=UnitInventoryModelTest`
Expected: PASS

---

### Task 2: Block unit delete when inventory exists

**Files:**
- Modify: `app/Livewire/Units/Index.php` (lines ~625-693)
- Modify: `tests/Feature/Units/UnitDeleteTest.php`

**Interfaces:**
- Consumes: `Unit::inventoryItems()` from Task 1

- [ ] **Step 1: Write failing test in `UnitDeleteTest.php`**

```php
public function test_it_blocks_delete_when_unit_has_inventory_items(): void
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $property = Property::factory()->create(['organization_id' => $organization->id, 'code' => 'EDIF-H']);
    $unit = Unit::factory()->create(['organization_id' => $organization->id, 'property_id' => $property->id]);
    UnitInventoryItem::factory()->create(['organization_id' => $organization->id, 'unit_id' => $unit->id]);

    Livewire::actingAs($user)
        ->test(Index::class, ['property' => $property])
        ->call('deleteUnit', $unit->id)
        ->assertHasErrors(['delete']);

    $this->assertNotSoftDeleted('units', ['id' => $unit->id]);
}
```

- [ ] **Step 2: Run test — expect FAIL**

Run: `./vendor/bin/sail test --filter=test_it_blocks_delete_when_unit_has_inventory_items`

- [ ] **Step 3: Update `Index.php`**

In `getUnitsPaginator()` add `'inventoryItems'` to `withCount`.
In `deletableUnitsQuery()` add `->whereDoesntHave('inventoryItems')`.
In `unitIsDeletable()` add `&& $unit->inventory_items_count === 0`.
In `unitHasOperationalHistory()` add `|| $unit->inventoryItems()->exists()`.

- [ ] **Step 4: Update `index.blade.php` deletable check**

Add `&& $unit->inventory_items_count === 0` to `$unitIsDeletable`.

- [ ] **Step 5: Run tests**

Run: `./vendor/bin/sail test --filter=UnitDeleteTest`
Expected: PASS

---

### Task 3: Unit Show page and route

**Files:**
- Create: `app/Livewire/Units/Show.php`
- Create: `resources/views/livewire/units/show.blade.php`
- Modify: `routes/web.php`
- Create: `lang/es/inventory.php`, `lang/en/inventory.php` (minimal keys for show)
- Test: `tests/Feature/Units/UnitInventoryShowTest.php`

**Interfaces:**
- Produces: route `properties.units.show`, component `Units\Show` with public `Property $property`, `Unit $unit`

- [ ] **Step 1: Write failing test**

```php
public function test_show_page_requires_units_view_permission(): void
{
    $org = Organization::factory()->create();
    $viewer = Role::findOrCreate('Lectura', 'web');
    $user = User::factory()->create(['organization_id' => $org->id]);
    $user->syncRoles([$viewer]);
    $property = Property::factory()->create(['organization_id' => $org->id, 'kind' => Property::KIND_BUILDING]);
    $unit = Unit::factory()->create(['organization_id' => $org->id, 'property_id' => $property->id]);

    $this->actingAs($user)
        ->get(route('properties.units.show', ['property' => $property, 'unit' => $unit]))
        ->assertForbidden();
}

public function test_show_page_renders_for_building_unit(): void
{
    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);
    $property = Property::factory()->create(['organization_id' => $org->id, 'kind' => Property::KIND_BUILDING]);
    $unit = Unit::factory()->create(['organization_id' => $org->id, 'property_id' => $property->id, 'code' => '101']);

    $this->actingAs($user)
        ->get(route('properties.units.show', ['property' => $property, 'unit' => $unit]))
        ->assertOk()
        ->assertSee('101');
}
```

- [ ] **Step 2: Run — expect FAIL** (route not defined)

- [ ] **Step 3: Add route in `web.php`** after `properties.units.index`:

```php
Route::get('/properties/{property}/units/{unit}', UnitsShow::class)
    ->middleware('permission:units.view')
    ->name('properties.units.show');
```

Import: `use App\Livewire\Units\Show as UnitsShow;`

- [ ] **Step 4: Create `Units\Show.php`**

```php
public function mount(Property $property, Unit $unit): void
{
    if (! (auth()->user()?->can('units.view') ?? false)) {
        abort(403);
    }
    if ($property->isStandaloneEntity()) {
        $this->redirectRoute('houses.show', ['property' => $property->id], navigate: false);
    }
    if ((int) $unit->property_id !== (int) $property->id) {
        abort(404);
    }
    if ((int) $unit->organization_id !== (int) auth()->user()?->organization_id) {
        abort(403);
    }
    $this->property = $property;
    $this->unit = $unit;
}
```

- [ ] **Step 5: Create `show.blade.php`** with page header, unit info cards, and:

```blade
<livewire:units.inventory-panel :unit="$unit" :key="'inventory-'.$unit->id" />
```

- [ ] **Step 6: Run tests — expect PASS**

---

### Task 4: InventoryPanel — CRUD items

**Files:**
- Create: `app/Livewire/Units/InventoryPanel.php`
- Create: `resources/views/livewire/units/inventory-panel.blade.php`
- Complete: `lang/es/inventory.php`, `lang/en/inventory.php`
- Test: `tests/Feature/Units/UnitInventoryPanelTest.php`

**Interfaces:**
- Consumes: `Unit $unit`, `UnitInventoryItem` from Task 1
- Produces: methods `openCreateForm()`, `saveItem()`, `openEditForm(int $id)`, `deleteItem(int $id)`

- [ ] **Step 1: Write failing CRUD test**

```php
public function test_it_creates_inventory_item_with_manage_permission(): void
{
    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);
    $property = Property::factory()->create(['organization_id' => $org->id]);
    $unit = Unit::factory()->create(['organization_id' => $org->id, 'property_id' => $property->id]);

    Livewire::actingAs($user)
        ->test(InventoryPanel::class, ['unit' => $unit])
        ->set('formName', 'Refrigerador')
        ->set('formQuantity', 1)
        ->set('formCondition', UnitInventoryItem::CONDITION_GOOD)
        ->call('saveItem')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('unit_inventory_items', [
        'unit_id' => $unit->id,
        'name' => 'Refrigerador',
        'condition' => 'good',
    ]);
}
```

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Implement `InventoryPanel.php`**

Key properties: `Unit $unit`, form fields (`formName`, `formQuantity`, `formCondition`, `formNotes`), `?int $editingItemId`, `bool $showForm`.

Rules:
```php
'formName' => ['required', 'string', 'max:255'],
'formQuantity' => ['required', 'integer', 'min:1', 'max:9999'],
'formCondition' => ['required', Rule::in([UnitInventoryItem::CONDITION_GOOD, ...])],
'formNotes' => ['nullable', 'string', 'max:2000'],
```

`saveItem()`: require `units.manage`; create or update scoped to unit.
`deleteItem($id)`: require `units.manage`; soft delete.
`render()`: load `$this->unit->inventoryItems()->withCount('documents')->orderBy('sort_order')->orderBy('name')->get()`.

- [ ] **Step 4: Build blade** — table with condition badges, empty state, modal for form.

- [ ] **Step 5: Run tests — expect PASS**

---

### Task 5: Photo upload per item

**Files:**
- Modify: `app/Livewire/Units/InventoryPanel.php`
- Modify: `resources/views/livewire/units/inventory-panel.blade.php`
- Test: extend `UnitInventoryPanelTest.php`

**Interfaces:**
- Consumes: `Document` model, `config('filesystems.documents_disk')`
- Produces: `uploadPhoto(int $itemId)` method

- [ ] **Step 1: Write failing photo test**

```php
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

public function test_it_uploads_photo_for_inventory_item(): void
{
    Storage::fake('public');
    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);
    $unit = Unit::factory()->create(['organization_id' => $org->id]);
    $item = UnitInventoryItem::factory()->create(['organization_id' => $org->id, 'unit_id' => $unit->id]);
    $file = UploadedFile::fake()->image('evidence.jpg');

    Livewire::actingAs($user)
        ->test(InventoryPanel::class, ['unit' => $unit])
        ->set('photoUploads.'.$item->id, $file)
        ->call('uploadPhoto', $item->id)
        ->assertHasNoErrors();

    $this->assertDatabaseHas('documents', [
        'documentable_type' => UnitInventoryItem::class,
        'documentable_id' => $item->id,
    ]);
}
```

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Implement `uploadPhoto`**

```php
public function uploadPhoto(int $itemId): void
{
    if (! (auth()->user()?->can('documents.upload') ?? false)) {
        abort(403);
    }
    $item = $this->unit->inventoryItems()->findOrFail($itemId);
    if ($item->documents()->count() >= 5) {
        throw ValidationException::withMessages(['photo' => __('inventory.validation.max_photos')]);
    }
    $this->validate(["photoUploads.{$itemId}" => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120']]);
    $disk = config('filesystems.documents_disk', 'public');
    $path = $this->photoUploads[$itemId]->store('documents/unitinventoryitem/'.$item->organization_id, $disk);
    Document::query()->create([...]);
}
```

Use `WithFileUploads` trait; `public array $photoUploads = []`.

- [ ] **Step 4: Add thumbnails in blade** — link to `route('documents.download', $doc)` for images.

- [ ] **Step 5: Run tests — expect PASS**

---

### Task 6: Link from units index

**Files:**
- Modify: `resources/views/livewire/units/index.blade.php`

- [ ] **Step 1: Add link on unit code**

```blade
<a href="{{ route('properties.units.show', ['property' => $property, 'unit' => $unit]) }}"
   class="font-medium uppercase text-blue-700 hover:underline">
    {{ $unit->code ?: __('common.no_code') }}
</a>
```

- [ ] **Step 2: Manual smoke** — navigate from units list to show page in browser.

---

### Task 7: Final verification

- [ ] Run: `./vendor/bin/sail test --filter=UnitInventory`
- [ ] Run: `./vendor/bin/sail test --filter=UnitDeleteTest`
- [ ] Run: `./vendor/bin/sail pint --dirty`
- [ ] All green.

---

## Plan Self-Review

| Spec requirement | Task |
|------------------|------|
| `unit_inventory_items` table | Task 1 |
| `UnitInventoryItem` + relations | Task 1 |
| Block unit delete | Task 2 |
| Show page `/properties/{property}/units/{unit}` | Task 3 |
| CRUD items | Task 4 |
| Photos via Document | Task 5 |
| Link from index | Task 6 |
| i18n es/en | Tasks 3–5 |
| Tests | All tasks |
| No new permissions | All tasks |
| Max 5 photos, images only | Task 5 |

No placeholders found. Types consistent across tasks.
