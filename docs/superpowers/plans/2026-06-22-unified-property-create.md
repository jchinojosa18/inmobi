# Unified Property Create Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Single "Nuevo inmueble" button with type-specific create flows and building bulk unit wizard.

**Architecture:** `Properties\CreateModal` wizard (picker → form); standalone types create Property+Unit in one transaction; buildings redirect to units bulk wizard. Model helpers centralize standalone detection.

**Tech Stack:** Laravel 11, Livewire 4, PHPUnit, Sail

**Spec:** `docs/superpowers/specs/2026-06-22-unified-property-create-design.md`

---

### Task 1: Model constants and helpers

**Files:**
- Modify: `app/Models/Property.php`
- Modify: `app/Models/Unit.php`
- Modify: `database/factories/PropertyFactory.php`
- Modify: `database/factories/UnitFactory.php`

Add kinds, `isStandaloneEntity()`, `kindLabel()` on Property.

### Task 2: Properties\CreateModal

**Files:**
- Create: `app/Livewire/Properties/CreateModal.php`
- Create: `resources/views/livewire/properties/create-modal.blade.php`
- Delete: `app/Livewire/Houses/CreateModal.php`
- Delete: `resources/views/livewire/houses/create-modal.blade.php`

Wizard with picker + forms; event `open-property-create`.

### Task 3: Properties Index integration

**Files:**
- Modify: `app/Livewire/Properties/Index.php`
- Modify: `resources/views/livewire/properties/index.blade.php`

Single button, edit-only form modal, kind labels, standalone "Ver" link.

### Task 4: Units bulk wizard

**Files:**
- Modify: `app/Livewire/Units/Index.php`
- Modify: `resources/views/livewire/units/index.blade.php`

Bulk modal, floor rows, preview, generate; `?bulk=1` auto-open; redirect standalone entities.

### Task 5: Standalone show + references

**Files:**
- Modify: `app/Livewire/Houses/Show.php`
- Modify: `resources/views/livewire/houses/show.blade.php`
- Modify: `routes/web.php`
- Modify: `app/Livewire/Dashboard/Index.php`
- Modify: `app/Livewire/Search/CommandPalette.php`

### Task 6: Tests

**Files:**
- Modify: `tests/Feature/Houses/StandaloneHouseFlowTest.php`
- Create: `tests/Feature/Properties/PropertyCreateModalTest.php`
- Create: `tests/Feature/Units/BulkGenerateUnitsTest.php`

Run: `./vendor/bin/sail test --filter=PropertyCreateModal`
Run: `./vendor/bin/sail test --filter=BulkGenerateUnits`
Run: `./vendor/bin/sail test --filter=StandaloneHouseFlow`
Run: `./vendor/bin/sail pint --dirty`
