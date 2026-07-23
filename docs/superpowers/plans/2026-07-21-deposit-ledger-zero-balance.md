# Deposit ledger zero balance — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show `DEPOSIT_HOLD` / `DEPOSIT_APPLY` rows in account statement with `paid = amount` and `balance = 0`.

**Architecture:** UI mapping only in `Contracts\Show::mapChargeToLedgerRow`; totals already exclude deposit types.

**Tech Stack:** Laravel 11, Livewire 4, PHPUnit via Sail.

## File map

| File | Responsibility |
|------|----------------|
| `app/Livewire/Contracts/Show.php` | Force deposit row paid/balance |
| `tests/Feature/Contracts/ContractShowDepositPendingTest.php` | Assert deposit row balance 0 |

### Task 1: Failing test + fix

**Files:**
- Modify: `tests/Feature/Contracts/ContractShowDepositPendingTest.php`
- Modify: `app/Livewire/Contracts/Show.php`

- [x] **Step 1: Write failing Livewire test** asserting deposit row `balance === 0.0` and `paid === amount`
- [x] **Step 2: Run test — expect fail**
- [x] **Step 3: In `mapChargeToLedgerRow`, if deposit type → `paid = amount`, `balance = 0`**
- [x] **Step 4: Run test — expect pass; pint --dirty**
