# Contract unit select + edit lock — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (or subagent-driven-development). TDD. Sail tests + pint.

**Goal:** Hide units with an active contract from the create select; on edit, unit and tenant are read-only and cannot change.

**Architecture:** Adjust `CreateModal` query + Blade; keep existing uniqueness validation as safety net.

**Tech stack:** Laravel 11, Livewire 4, PHPUnit Feature tests via Sail.

---

### Task 1: Tests (RED)

**Files:**
- Modify: `tests/Feature/Contracts/ContractCreateModalTest.php`

1. Create: unit with active contract does not appear in select HTML options; free unit does.
2. Edit: `save` with tampered `unit_id`/`tenant_id` leaves original associations.
3. Edit: assert disabled/readonly markup for unit and tenant (or absence of other unit options).

Run: `./vendor/bin/sail test --filter=ContractCreateModalTest` → FAIL until implementation.

### Task 2: Implementation (GREEN)

**Files:**
- Modify: `app/Livewire/Contracts/CreateModal.php`
- Modify: `resources/views/livewire/contracts/create-modal.blade.php`

1. `render()`: if create, `whereDoesntHave` active contracts; if edit, load current unit (+ current tenant) for display.
2. `open()`: only preselect unit if not occupied by active contract.
3. `save()`: on edit, keep `$contract->unit_id` / `tenant_id`; do not associate from request.
4. Blade: when `$isEdit`, show disabled selects or static labels for unit/tenant.

Run tests + `./vendor/bin/sail pint --dirty`.
