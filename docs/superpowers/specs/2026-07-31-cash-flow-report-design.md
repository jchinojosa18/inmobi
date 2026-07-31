# Flujo de caja congruente (UI + CSV + plaza) — Design Spec

**Date:** 2026-07-31  
**Status:** Approved  
**Related:** `docs/AI_ONBOARDING.md` (§4.4 Reportes por allocations), `OperatingIncomeService`, `Reports\CashFlow`, `CashFlowCsvExportController`, `BuildMonthCloseSnapshotAction`, `Expenses\Index` (plaza scope)

## Goal

Hacer que el reporte de flujo (`/reports/flow`) sea **consistente y preciso**: mismos números en UI y CSV, scope de plaza alineado con egresos, timezone uniforme, y comparación con snapshot de cierre sin falsos mismatches. Filtros v1: rango de fechas + plaza (selector global).

## Decisions (brainstorming)

| Tema | Decisión |
|------|----------|
| Prioridad | Congruencia de números + filtros existentes bien hechos (A+B); UI solo lo necesario |
| Filtros v1 | Solo `date_from` / `date_to` + plaza vía `TenantContext` |
| Enfoque | Servicio único `CashFlowReportService` consumido por Livewire y CSV |
| Egresos sin unidad + plaza | Incluir `unit_id` null (igual que `/expenses`) |
| Snapshot de cierre | Banner solo si rango = mes calendario completo **y** no hay plaza activa |
| Month close snapshot | Sin cambios; sigue org-wide |

## Out of Scope (v1)

- Filtros por categoría de egreso, tipo de cargo, propiedad/unidad o presets de periodo
- Desglose por categoría en snapshot de cierre mensual
- P&L por unidad
- Rediseño visual grande de la pantalla
- Cambios a `BuildMonthCloseSnapshotAction`

## Architecture

### Fuente de verdad

```text
date_from/date_to + org + plaza?
        │
        ▼
CashFlowReportService::build(...)
        │
        ├── OperatingIncomeService (allocations operativas)
        ├── Expense query (spent_at + plaza scope)
        └── MonthClose snapshot (solo si aplica)
        │
        ├── Reports\CashFlow (Livewire)
        └── CashFlowCsvExportController
```

### `CashFlowReportService` (nuevo, `app/Support`)

Entrada: `organizationId`, `dateFrom`, `dateTo` (`CarbonImmutable` en `America/Tijuana`), `?plazaId`.

Salida (array/DTO):

| Campo | Contenido |
|-------|-----------|
| `incomeTotal` / `expenseTotal` / `netTotal` | Totales redondeados a 2 decimales |
| `incomeByType` | Totales por tipo operativo |
| `incomeDetails` | Filas de allocations (detalle) |
| `expenses` | Colección de egresos del rango |
| `expensesByCategory` | Subtotales por nombre de categoría FK |
| `incomeCount` / `expenseCount` | Conteos |
| `operatingChargeTypes` | Lista de tipos operativos |
| `closedMonthSnapshot` | Snapshot o `null` |
| `snapshotMatches` | `bool` o `null` si no hay snapshot comparable |

Livewire y CSV **no** recalculan totales; solo formatean/exportan.

### `OperatingIncomeService` (ajuste)

- Unificar joins: `LEFT JOIN` units/properties en **detalle y totales** (hoy totales usan `INNER JOIN` → riesgo de divergencia).
- Seguir filtrando `whereIn(charges.type, operatingChargeTypes())`.
- Seguir excluyendo depósitos vía tipos operativos (sin `DEPOSIT_HOLD` / `DEPOSIT_APPLY`).
- Rango por `payments.paid_at` entre start/end del día en timezone de reporte.
- Filtro plaza: `properties.plaza_id = ?` cuando `plazaId` no es null (contratos sin propiedad/unidad no entran en vista de plaza; en vista org completa sí pueden aparecer vía LEFT JOIN).
- Efecto colateral esperado: `BuildMonthCloseSnapshotAction` (que ya usa este servicio) hereda totales más correctos en cierres **nuevos**; no se reescriben snapshots históricos.

### Egresos — scope de plaza

Misma regla que `Expenses\Index::applyPlazaScope` (sin filtro de assignment):

- Sin plaza: todos los egresos de la org en el rango `spent_at`.
- Con plaza: `(unit_id IS NULL) OR (unit.property.plaza_id = plazaId)`.

### Snapshot

Mostrar banner de match/mismatch **solo si**:

1. `date_from` = primer día del mes y `date_to` = último día del mismo mes, y
2. `plazaId === null`, y
3. Existe `MonthClose` para ese `YYYY-MM`.

Comparar `ingresos_operativos`, `egresos`, `neto` del snapshot vs totales del servicio.

### Timezone

- Parsear y acotar rango siempre en `America/Tijuana` (UI y CSV).
- Hoy el CSV usa `CarbonImmutable::parse` sin timezone → corregir.

## UI

- Mantener estructura actual: header + export, filtros de fecha, nota de tipos operativos, stats, desglose ingresos, tabla allocations, egresos por categoría, tabla egresos.
- Plaza: no agregar select local; usar selector global existente.
- Banner snapshot: solo cuando las reglas anteriores lo permiten; copy i18n existente.
- Sin filtros nuevos en v1.

## CSV

- Misma entrada al servicio → mismos totales en `RESUMEN`.
- Secciones actuales: allocations, ingresos por tipo, egresos, resumen.
- Categoría de egreso vía `expenseCategory.name`.

## Reglas de negocio (sin cambio conceptual)

- Ingreso operativo = suma de `payment_allocations.amount` en tipos configurados (`config/reporting.operating_income_charge_types` o default RENT/PENALTY/SERVICE/OTHER/ADJUSTMENT).
- No usar `payments.amount` bruto.
- Neto = ingresos − egresos.
- Cierre mensual org-wide sigue siendo la referencia cuando el reporte se ve sin plaza y en mes completo.

## Tests mínimos

- UI y CSV: mismos `TOTAL_INGRESOS` / `TOTAL_EGRESOS` / `NETO` para el mismo rango.
- Con plaza: egreso `unit_id` null **sí** entra; egreso de otra plaza **no**.
- Con plaza + mes cerrado: no se muestra texto de match/mismatch del snapshot.
- Sin plaza + mes cerrado completo: match con snapshot (regresión existente).
- Suma de `incomeByType` === suma de `incomeDetails` (regresión join).
- Feature tests existentes de depósitos excluidos y split RENT/PENALTY se mantienen.

## Global Constraints

- Sail: `./vendor/bin/sail` para test/pint.
- Multi-tenant: `organization_id` en queries.
- Permisos existentes: `reports.view`, `reports.export`; sin permisos nuevos.
- Fechas UI: `d/m/Y` vía `DateDisplay` / `x-ui.display-date`.
- Diff mínimo; lógica en Support, no duplicada en Livewire.
- Verificar: `./vendor/bin/sail test --filter=CashFlow` + `./vendor/bin/sail pint --dirty`.
