# Egresos con categorías FK y vínculo a unidad/contrato — Design Spec

**Date:** 2026-07-31  
**Status:** Approved  
**Related:** `docs/AI_ONBOARDING.md` (Expense, ExpenseCategory, MonthCloseGuard, CashFlow), `app/Models/Expense.php`, `app/Livewire/Settings/Index.php`

## Goal

Normalizar los egresos para reportes consistentes: cada egreso pertenece a una categoría del catálogo de la organización (FK obligatoria), puede asignarse a una unidad y opcionalmente a un contrato. Eliminar el campo texto libre `expenses.category`.

## Decisions (brainstorming)

| Tema | Decisión |
|------|----------|
| Categorías | Por organización, editable en `/settings` (opción C) |
| FK obligatoria | `expenses.expense_category_id` → `expense_categories.id` |
| Defaults al crear org | MANTENIMIENTO, LIMPIEZA, SERVICIO, REEMBOLSO DEPÓSITO |
| Categorías sistema | `is_system = true`; no eliminables; desactivables solo si no aplica |
| Unidad | `unit_id` opcional; select en captura (no solo autocomplete) |
| Contrato | `contract_id` opcional; coherente con `unit_id` |
| Inquilino | Sin FK directo; derivado vía `contract.tenant` en reportes |
| Texto libre | Eliminar `expenses.category` tras migración |
| Finiquito | Usa categoría sistema REEMBOLSO DEPÓSITO + `contract_id` en columna |

## Out of Scope (v1)

- Desglose por categoría en snapshot de cierre mensual (fase 2)
- P&L por unidad (ingresos − egresos)
- Portal inquilino / cargos reembolsables al inquilino
- Catálogo global sin `organization_id`

## Architecture

### Modelo de datos

**`expense_categories`** (cambios):

- Agregar `is_system` boolean default `false`.
- Relación `hasMany(Expense::class)`.

**`expenses`** (cambios):

- Agregar `expense_category_id` FK NOT NULL (tras backfill).
- Agregar `contract_id` FK nullable → `contracts`.
- Eliminar columna `category` (string).

**Integridad:**

- `expense_category_id` debe pertenecer a la misma `organization_id` del egreso.
- `unit_id` opcional; si presente, misma org (y plaza en UI).
- Si `contract_id` presente: mismo org; `contract.unit_id === expense.unit_id` (autocompletar unidad al elegir contrato).
- Sin `tenant_id` en `expenses`.

### Categorías por defecto

| Nombre | `is_system` | Uso |
|--------|-------------|-----|
| MANTENIMIENTO | true | Captura manual |
| LIMPIEZA | true | Captura manual |
| SERVICIO | true | Captura manual |
| REEMBOLSO DEPÓSITO | true | Finiquito automático |

El admin puede agregar categorías custom en settings. Eliminar bloqueado si `is_system` o si tiene egresos; desactivar (`is_active = false`) en su lugar.

### Migración de datos

1. Agregar columnas nuevas (nullable temporalmente).
2. Por organización: sembrar 4 categorías si faltan.
3. Mapear `expenses.category` → `expense_category_id` (case-insensitive); crear categoría ad-hoc para strings huérfanos.
4. `"Refund deposit"` → REEMBOLSO DEPÓSITO; promover `meta.contract_id` a `contract_id`.
5. `expense_category_id` NOT NULL; drop `category`.

### Piezas de código

| Pieza | Rol |
|-------|-----|
| `SeedDefaultExpenseCategoriesAction` | Sembrar 4 categorías por org; idempotente |
| `RegisterExpenseAction` | Crear egreso con validación org/unit/contract/category |
| `Expense` model | Relaciones `expenseCategory`, `contract`; auditable attrs |
| `ExpenseCategory` model | `hasMany expenses`, scopes `active()`, `system()` |
| `RegisterController` | Invocar seed de categorías al crear org |
| `ProcessContractSettlementAction` | Egreso reembolso vía FK + `contract_id` |
| `DepositBalanceService` | Buscar reembolsos por categoría sistema + `contract_id` |
| `Expenses\Index`, `QuickRegisterModal` | Select categoría/unidad/contrato; delegar a Action |
| `Settings\Index` | Proteger delete de categorías en uso / sistema |
| `Reports\CashFlow` | Totales y listado por categoría FK |

## UI

### Captura (`/expenses` + modal rápido)

- **Categoría:** select obligatorio, solo `is_active = true`.
- **Unidad:** select con opción “General / sin unidad”; unidades de la org filtradas por plaza.
- **Contrato:** select opcional visible si hay unidad; contratos de esa unidad (activos + recientes).
- Eliminar merge de categorías históricas en texto libre.

### Settings

- CRUD actual se mantiene.
- Delete: bloquear si `is_system` o `expenses_count > 0` → mensaje sugerir desactivar.
- Desactivar: no aparece en select de captura; histórico conserva FK.

## Reportes (fase 1)

- Flujo de caja: columna categoría vía relación; subtotal por categoría en el periodo.
- `/expenses`: filtro por `expense_category_id`.
- Export CSV: categoría normalizada.

## Reglas de negocio

- `MonthCloseGuard` sin cambio (usa `spent_at`).
- Finiquito: categoría REEMBOLSO DEPÓSITO + `contract_id` + `unit_id` del contrato.
- Auditoría: incluir `expense_category_id`, `contract_id` en atributos auditables.

## Tests mínimos

- Seed idempotente en org nueva y backfill.
- No guardar egreso sin categoría activa de la org.
- Contrato de otra unidad → validación falla.
- Finiquito crea egreso con categoría sistema y `contract_id`.
- No eliminar categoría sistema ni con egresos.
- Cash flow agrupa/totaliza por categoría.
- Migración mapea strings históricos correctamente.

## Global Constraints

- Sail obligatorio: `./vendor/bin/sail` para artisan, test, pint.
- Multi-tenant: `organization_id` en todos los modelos scoped.
- Permisos existentes: `expenses.*`, `expense_categories.manage`; sin permisos nuevos.
- Formato fechas UI: `d/m/Y` vía `DateDisplay` / `x-ui.display-date`.
- Diff mínimo; lógica de negocio en Actions, no duplicada en Livewire.
