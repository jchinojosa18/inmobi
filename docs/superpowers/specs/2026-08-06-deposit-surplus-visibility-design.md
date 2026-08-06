# Visibilidad del sobrante de depósito (finiquito) — Design Spec

**Date:** 2026-08-06  
**Status:** Approved  
**Related:** contrato local #95 (`deposit_refund` 2500), `SettlementWizard`, `Expenses\Index`, `ProcessContractSettlementAction`, `DepositBalanceService`

## Goal

Que el usuario vea de forma explícita el **sobrante de depósito** que se devolverá (o ya se devolvió) al finiquitar, tanto **antes de confirmar** como **después**, y que el gasto de reembolso sea reconocible en **Gastos** con enlace al contrato (y viceversa).

## Out of Scope

- Flujo “pendiente de pagar al inquilino” / marcar reembolso como pagado aparte
- Cambiar la lógica de cálculo en `ProcessContractSettlementAction` (el preview solo refleja la misma fórmula)
- Editar o anular gastos `REEMBOLSO DEPÓSITO` desde esta feature
- Cambios al PDF de finiquito (ya muestra “Devolución de depósito”)
- Cabecera del contrato fuera del panel Finiquito
- Componentes UI genéricos nuevos

## User Stories

1. Antes de confirmar el finiquito, veo **“Sobrante a devolver”** actualizado al editar conceptos de salida.
2. En un contrato finiquitado con sobrante (p. ej. #95), veo **“Sobrante / devolución: $X”** y un link al gasto.
3. En Gastos, un reembolso de depósito se distingue con badge y link al contrato.

## Behavior

### Fórmulas (alineadas al finiquito real)

Sea:

- `available` = `DepositBalanceService::availableDepositAmount($contract)`
- `outstanding` = `DepositBalanceService::outstandingBalanceExcludingDepositHold($contract)`
- `conceptsTotal` = suma de montos de conceptos de salida del form con `amount > 0` y descripción no vacía
- `projectedOutstanding` = `outstanding + conceptsTotal`

Entonces:

- **Sobrante a devolver (preview):** `max(0, available − projectedOutstanding)`
- **Saldo por cobrar (preview, opcional en UI):** `max(0, projectedOutstanding − available)`

Tras finiquito, el sobrante mostrado es el **ya registrado**:

- Preferir `deposit_refund` del batch en `contract.meta.settlements` / `refundedDepositAmount`
- Link al gasto: `refund_expense_id` del mismo batch si existe; si no, listado filtrado por contrato

Si sobrante es `0`, mostrar `$0.00` sin énfasis visual (sin badge verde / callout).

### Contrato — panel Finiquito (`settlement-wizard`)

**Contrato operable (formulario visible):**

- En la caja de resumen (o debajo, junto al form): línea **“Sobrante a devolver”** con el preview.
- Se recalcula en cada re-render Livewire al cambiar conceptos (`wire:model.blur` ya existente).
- No se persiste el preview; es solo display.

**Contrato ended / cancelled (formulario bloqueado):**

- Línea **“Sobrante / devolución”** = monto reembolsado (`refundedDeposit`).
- Si `refund_expense_id` (o hay gasto de reembolso ligado al contrato): link **“Ver gasto de devolución”** → `route('expenses.index', ['contractFilter' => $contractId])` (o nombre de query acordado en implementación).
- Mantener las líneas actuales (registrado / aplicado / devuelto / disponible / adeudo).

**Header colapsado del acordeón:** no obligatorio mostrar el sobrante ahí en v1; basta en el cuerpo abierto. (Opcional mínimo: no tocar el header compacto actual.)

### Gastos — listado

Para cada fila donde:

- categoría sistema `REEMBOLSO DEPÓSITO`, **o**
- `meta.reason === 'contract_settlement'`

mostrar:

- Badge **“Devolución depósito”** junto a la categoría
- Si `contract_id` no es null: link **“Contrato #N”** → `route('contracts.show', $contractId)`

**Filtro por contrato (query string):**

- Propiedad Livewire p. ej. `contractFilter` (string/int vacío = sin filtro)
- Incluir en `$queryString` para que el link desde el contrato abra el listado filtrado
- No hace falta un select visible de contratos en v1; el query basta

Eager-load: `expenseCategory`, `contract:id` (y lo mínimo para el link).

## Architecture

### Enfoque

Solo UI + i18n + filtro query en Gastos. Sin Action nueva. Sin migración.

| Pieza | Cambio |
|-------|--------|
| `SettlementWizard` + blade | computed preview; post-settlement label + link |
| `lang/{es,en}/contracts.php` | claves sobrante / link gasto |
| `Expenses\Index` + blade | badge, link contrato, `contractFilter` |
| `lang/{es,en}/finance.php` (o expenses) | badge |
| Tests Feature | wizard preview; ended refund link; expenses badge |
| `docs/AI_ONBOARDING.md` §4.3 | una línea de visibilidad |

### Alternativas descartadas

| Enfoque | Motivo |
|---------|--------|
| Snapshot-only desde meta sin preview | No cumple “antes de confirmar” |
| Estado “pendiente de devolver” | YAGNI; el gasto ya es la evidencia |
| Cabecera del contrato | Usuario priorizó panel Finiquito + Gastos |

## Acceptance Criteria

1. Con depósito disponible 7500 y adeudo+conceptos 5000, el preview muestra sobrante **2500** antes de confirmar.
2. Con adeudo+conceptos ≥ disponible, preview de sobrante es **0**.
3. Contrato ended con `deposit_refund` 2500 muestra “Sobrante / devolución: $2,500” y link a `expenses.index?contractFilter={id}` (filtro aplica; la fila del reembolso aparece).
4. Fila de gasto `REEMBOLSO DEPÓSITO` muestra badge y link al contrato.
5. Pint + tests Feature relacionados en verde vía Sail.
6. No cambia montos creados por `ProcessContractSettlementAction`.

## Test Plan

- Livewire Feature: `SettlementWizard` con depósito e outstanding conocidos; assertSee del monto de sobrante al setear conceptos.
- Livewire Feature: contrato ended con expense reembolso; assertSee label + href a expenses con filtro.
- Feature `Expenses\Index`: expense de reembolso; assertSee badge y link a `contracts.show`.
- Reusar fixtures/patrones de `SettlementWizardAccordionTest` / settlement action tests.

## Open Decisions (resolved)

- **Momento:** preview + post-finiquito (opción C).
- **Ubicación post:** panel Finiquito **y** Gastos con badge + links bidireccionales (opción C).
- **Enfoque técnico:** solo UI (opción 1).
