# Inventory Multi-Photo Upload Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow selecting and uploading multiple inventory item photos in one submit, with all-or-nothing validation against the existing 5-photo cap.

**Architecture:** Keep logic in `Units\InventoryPanel`. Change `photoUploads.{itemId}` from a single `TemporaryUploadedFile` to an array, add `multiple` on the file input, validate the batch, and persist each `Document` inside a DB transaction. No schema, route, or permission changes.

**Tech Stack:** Laravel 11, Livewire 4 (`WithFileUploads`), Tailwind/Alpine in Blade, Sail for tests/pint.

## Global Constraints

- Run all commands via `./vendor/bin/sail` (never bare `php artisan`).
- Max photos per item remains **5** (`InventoryPanel::MAX_PHOTOS_PER_ITEM`).
- MIME/size unchanged: JPG/PNG, max 5120 KB each.
- If batch would exceed remaining slots → reject **entire** batch (no partial create).
- If any file in the batch fails validation → reject **entire** batch.
- Keep choose-files → click upload UX (no auto-upload).
- One audit log entry per created photo (`inventory.photo_uploaded`).
- Diff stays in `InventoryPanel`, its Blade view, `lang/{es,en}/inventory.php`, and `UnitInventoryPanelTest`.
- Spec: `docs/superpowers/specs/2026-07-21-inventory-multi-photo-upload-design.md`.
- Tests: `./vendor/bin/sail test --filter=UnitInventoryPanel`; format: `./vendor/bin/sail pint --dirty`.

---

## File Map

| File | Responsibility |
|------|----------------|
| `tests/Feature/Units/UnitInventoryPanelTest.php` | Multi-upload, batch-over-cap, invalid-in-batch, array-shaped single upload |
| `app/Livewire/Units/InventoryPanel.php` | Array validation + transactional multi-create in `uploadPhoto` |
| `resources/views/livewire/units/inventory-panel.blade.php` | `multiple` input, multi-name preview, error display for array keys |
| `lang/es/inventory.php` | Plural upload copy |
| `lang/en/inventory.php` | Plural upload copy |

---

### Task 1: Failing tests for multi-upload and batch rejection

**Files:**
- Modify: `tests/Feature/Units/UnitInventoryPanelTest.php`

**Interfaces:**
- Consumes: `InventoryPanel::uploadPhoto(int $itemId)`, `photoUploads.{itemId}` as `array` of `UploadedFile`
- Produces: failing tests that define batch success / all-or-nothing failures

- [ ] **Step 1: Update the existing single-photo test to set an array**

In `test_it_uploads_photo_for_inventory_item`, change the Livewire set to an array of one file:

```php
->set('photoUploads.'.$item->id, [$file])
```

Also update `test_it_blocks_more_than_five_photos_per_item`:

```php
->set('photoUploads.'.$item->id, [$file])
```

- [ ] **Step 2: Add multi-upload success test**

Append to `UnitInventoryPanelTest`:

```php
public function test_it_uploads_multiple_photos_for_inventory_item(): void
{
    Storage::fake('public');
    config(['filesystems.documents_disk' => 'public']);

    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $unit = Unit::factory()->create(['organization_id' => $organization->id]);
    $item = UnitInventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
    ]);
    $files = [
        UploadedFile::fake()->image('evidence-1.jpg'),
        UploadedFile::fake()->image('evidence-2.jpg'),
    ];

    Livewire::actingAs($user)
        ->test(InventoryPanel::class, ['unit' => $unit])
        ->set('photoUploads.'.$item->id, $files)
        ->call('uploadPhoto', $item->id)
        ->assertHasNoErrors()
        ->assertDispatched('inventory-photo-uploaded');

    $this->assertSame(2, Document::query()
        ->where('documentable_type', UnitInventoryItem::class)
        ->where('documentable_id', $item->id)
        ->where('type', 'UNIT_INVENTORY_PHOTO')
        ->count());
}
```

- [ ] **Step 3: Add batch-over-cap rejection test**

```php
public function test_it_rejects_entire_batch_when_photos_would_exceed_limit(): void
{
    Storage::fake('public');
    config(['filesystems.documents_disk' => 'public']);

    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $unit = Unit::factory()->create(['organization_id' => $organization->id]);
    $item = UnitInventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
    ]);

    Document::factory()->count(3)->create([
        'organization_id' => $organization->id,
        'documentable_type' => UnitInventoryItem::class,
        'documentable_id' => $item->id,
        'type' => 'UNIT_INVENTORY_PHOTO',
    ]);

    $files = [
        UploadedFile::fake()->image('a.jpg'),
        UploadedFile::fake()->image('b.jpg'),
        UploadedFile::fake()->image('c.jpg'),
    ];

    Livewire::actingAs($user)
        ->test(InventoryPanel::class, ['unit' => $unit])
        ->set('photoUploads.'.$item->id, $files)
        ->call('uploadPhoto', $item->id)
        ->assertHasErrors(['photoUploads.'.$item->id]);

    $this->assertSame(3, Document::query()
        ->where('documentable_type', UnitInventoryItem::class)
        ->where('documentable_id', $item->id)
        ->count());
}
```

- [ ] **Step 4: Add invalid-file-in-batch rejection test**

```php
public function test_it_rejects_entire_batch_when_one_file_is_invalid(): void
{
    Storage::fake('public');
    config(['filesystems.documents_disk' => 'public']);

    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $unit = Unit::factory()->create(['organization_id' => $organization->id]);
    $item = UnitInventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
    ]);

    $files = [
        UploadedFile::fake()->image('ok.jpg'),
        UploadedFile::fake()->create('bad.pdf', 100, 'application/pdf'),
    ];

    Livewire::actingAs($user)
        ->test(InventoryPanel::class, ['unit' => $unit])
        ->set('photoUploads.'.$item->id, $files)
        ->call('uploadPhoto', $item->id)
        ->assertHasErrors();

    $this->assertSame(0, Document::query()
        ->where('documentable_type', UnitInventoryItem::class)
        ->where('documentable_id', $item->id)
        ->count());
}
```

- [ ] **Step 5: Run tests to verify they fail for the right reason**

Run:

```bash
./vendor/bin/sail test --filter=UnitInventoryPanel
```

Expected: `test_it_uploads_multiple_photos_for_inventory_item` fails (creates 0 or 1 docs, or validation error because value is treated as non-array / non-image). Batch rejection tests may fail open (partial upload) or with wrong error shape until Task 2. Do **not** implement production code yet.

- [ ] **Step 6: Commit**

```bash
git add tests/Feature/Units/UnitInventoryPanelTest.php
git commit -m "$(cat <<'EOF'
Add failing tests for inventory multi-photo upload.

EOF
)"
```

---

### Task 2: Implement multi-file `uploadPhoto`

**Files:**
- Modify: `app/Livewire/Units/InventoryPanel.php`

**Interfaces:**
- Consumes: `WithFileUploads`, `Document`, `AuditLogger`, `MAX_PHOTOS_PER_ITEM = 5`
- Produces: `uploadPhoto(int $itemId): void` accepting `photoUploads[$itemId]` as `array<int, TemporaryUploadedFile>`

- [ ] **Step 1: Add DB facade import**

At the top of `InventoryPanel.php`, add:

```php
use Illuminate\Support\Facades\DB;
```

- [ ] **Step 2: Replace `uploadPhoto` with array-aware implementation**

Replace the entire `uploadPhoto` method with:

```php
public function uploadPhoto(int $itemId): void
{
    if (! (auth()->user()?->can('documents.upload') ?? false)) {
        abort(403);
    }

    $item = $this->unit->inventoryItems()->findOrFail($itemId);
    $key = 'photoUploads.'.$itemId;
    $uploads = $this->photoUploads[$itemId] ?? null;

    if (! is_array($uploads)) {
        $uploads = $uploads !== null ? [$uploads] : [];
        $this->photoUploads[$itemId] = $uploads;
    }

    $existingCount = $item->documents()->count();
    $incomingCount = count($uploads);

    if ($incomingCount < 1) {
        throw ValidationException::withMessages([
            $key => __('inventory.validation.photo_required'),
        ]);
    }

    if ($existingCount + $incomingCount > self::MAX_PHOTOS_PER_ITEM) {
        throw ValidationException::withMessages([
            $key => __('inventory.validation.max_photos'),
        ]);
    }

    $this->validate([
        $key => ['required', 'array', 'min:1'],
        $key.'.*' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
    ], [
        $key.'.required' => __('inventory.validation.photo_required'),
        $key.'.min' => __('inventory.validation.photo_required'),
        $key.'.*.required' => __('inventory.validation.photo_required'),
        $key.'.*.image' => __('inventory.validation.photo_invalid'),
        $key.'.*.mimes' => __('inventory.validation.photo_invalid'),
        $key.'.*.max' => __('inventory.validation.photo_invalid'),
    ]);

    $disk = (string) config('filesystems.documents_disk', 'public');

    DB::transaction(function () use ($item, $uploads, $disk, $itemId): void {
        foreach ($uploads as $upload) {
            $path = $upload->store('documents/unitinventoryitem/'.$item->organization_id, $disk);

            Document::query()->create([
                'organization_id' => (int) $item->organization_id,
                'documentable_type' => UnitInventoryItem::class,
                'documentable_id' => $item->id,
                'path' => $path,
                'mime' => $upload->getMimeType() ?: 'image/jpeg',
                'size' => $upload->getSize() ?: 0,
                'type' => 'UNIT_INVENTORY_PHOTO',
                'tags' => ['inventory', 'photo'],
                'meta' => [
                    'disk' => $disk,
                    'uploaded_at' => now()->toISOString(),
                ],
            ]);

            app(AuditLogger::class)->log(
                action: 'inventory.photo_uploaded',
                auditable: $item,
                summary: __('inventory.audit.photo_uploaded', ['id' => $item->id]),
                meta: [
                    'unit_id' => $this->unit->id,
                    'item_id' => $item->id,
                ],
            );
        }
    });

    unset($this->photoUploads[$itemId]);
    $this->photoUploadInputKeys[$itemId] = ($this->photoUploadInputKeys[$itemId] ?? 0) + 1;
    $this->resetValidation($key);

    $this->dispatch('inventory-photo-uploaded');
}
```

Update the PHPDoc on `$photoUploads` to:

```php
/**
 * @var array<int, array<int, mixed>|mixed>
 */
public array $photoUploads = [];
```

- [ ] **Step 3: Run panel tests**

Run:

```bash
./vendor/bin/sail test --filter=UnitInventoryPanel
```

Expected: PASS for all photo upload tests including the new multi/batch cases.

- [ ] **Step 4: Commit**

```bash
git add app/Livewire/Units/InventoryPanel.php
git commit -m "$(cat <<'EOF'
Support multi-file inventory photo uploads with batch validation.

EOF
)"
```

---

### Task 3: Blade UI + i18n plural copy

**Files:**
- Modify: `resources/views/livewire/units/inventory-panel.blade.php`
- Modify: `lang/es/inventory.php`
- Modify: `lang/en/inventory.php`

**Interfaces:**
- Consumes: `photoUploads.{id}` as array, `uploadPhoto`, `inventory-photo-uploaded` event
- Produces: multi-select file input + plural labels + selected-files preview

- [ ] **Step 1: Update ES strings**

In `lang/es/inventory.php`, change:

```php
'upload_photo' => 'Subir fotos',
'choose_photo' => 'Elegir archivos',
'uploading_photo' => 'Subiendo fotos...',
'messages' => [
    // keep other keys; only change:
    'photo_uploaded' => 'Fotos subidas correctamente.',
```

Keep `validation.photo_required` as: `'Selecciona al menos una imagen para subir.'`

- [ ] **Step 2: Update EN strings**

In `lang/en/inventory.php`, change:

```php
'upload_photo' => 'Upload photos',
'choose_photo' => 'Choose files',
'uploading_photo' => 'Uploading photos...',
```

And:

```php
'photo_uploaded' => 'Photos uploaded successfully.',
```

```php
'photo_required' => 'Select at least one image to upload.',
```

- [ ] **Step 3: Enable `multiple` and multi-file preview in Blade**

In `resources/views/livewire/units/inventory-panel.blade.php`, replace the upload block’s Alpine wrapper + input (the `x-data="{ fileName: '' }"` section) with:

```blade
<div
    x-data="{ fileLabel: '' }"
    x-on:inventory-photo-uploaded.window="fileLabel = ''"
    class="flex flex-col gap-2"
>
    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
        <input
            id="{{ $photoInputId }}"
            type="file"
            multiple
            wire:key="inventory-photo-upload-{{ $galleryItem->id }}-{{ $photoUploadInputKeys[$galleryItem->id] ?? 0 }}"
            wire:model="photoUploads.{{ $galleryItem->id }}"
            accept=".jpg,.jpeg,.png"
            x-on:change="
                const files = Array.from($event.target.files || []);
                fileLabel = files.map(file => file.name).join(', ');
            "
            class="sr-only"
        >
        <label
            for="{{ $photoInputId }}"
            class="inline-flex min-h-10 w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus-within:outline-none focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2 sm:min-h-0 sm:w-auto sm:px-3 sm:py-1.5 sm:text-xs"
        >
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            {{ __('inventory.choose_photo') }}
        </label>
        <x-ui.button
            type="button"
            size="sm"
            variant="secondary"
            wire:click="uploadPhoto({{ $galleryItem->id }})"
            wire:loading.attr="disabled"
            wire:target="photoUploads.{{ $galleryItem->id }},uploadPhoto"
            class="min-h-10 w-full sm:min-h-0 sm:w-auto"
        >
            {{ __('inventory.upload_photo') }}
        </x-ui.button>
    </div>
    <p
        x-show="fileLabel"
        x-text="fileLabel"
        x-cloak
        class="truncate text-xs text-slate-500"
    ></p>
</div>
```

- [ ] **Step 4: Show array validation errors under the upload control**

Replace the single `@error('photoUploads.'.$galleryItem->id)` block with:

```blade
@php
    $photoErrorMessages = collect($errors->getMessages())
        ->filter(fn ($messages, $errorKey) => str_starts_with((string) $errorKey, 'photoUploads.'.$galleryItem->id))
        ->flatten()
        ->unique()
        ->values();
@endphp
@foreach ($photoErrorMessages as $message)
    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
@endforeach
```

Keep the existing `wire:loading` uploading message immediately after.

- [ ] **Step 5: Re-run tests + pint**

```bash
./vendor/bin/sail test --filter=UnitInventoryPanel
./vendor/bin/sail pint --dirty
```

Expected: all PASS; Pint reports no remaining dirty style issues.

- [ ] **Step 6: Commit**

```bash
git add resources/views/livewire/units/inventory-panel.blade.php lang/es/inventory.php lang/en/inventory.php
git commit -m "$(cat <<'EOF'
Allow multi-select inventory photos in the gallery upload UI.

EOF
)"
```

---

## Self-Review

| Spec requirement | Task |
|------------------|------|
| `multiple` input + choose → upload | Task 3 |
| Array bind + validate batch | Task 2 |
| Reject when `current + batch > 5` | Task 1 + 2 |
| Reject when any file invalid | Task 1 + 2 |
| Transactional N `Document` creates | Task 2 |
| Audit per photo | Task 2 |
| Plural i18n | Task 3 |
| Tests for multi / over-cap / invalid / single | Task 1 |
| No schema/permission changes | All tasks (none added) |

No TBD/TODO placeholders. Method name stays `uploadPhoto(int $itemId): void` across tasks. Property remains `photoUploads`.
