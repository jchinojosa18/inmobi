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
- Cards informativas de depósitos / ingreso bruto con depósitos → **Fase 2** (abajo)

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

---

## Fase 2 — Cards informativas de depósitos (no implementada en v1)

**Status:** Spec only (aprobado 2026-07-31)  
**Goal:** Mostrar movimiento de garantía en el reporte sin alterar la congruencia operativa ni el snapshot de cierre.

### Decisiones

| Tema | Decisión |
|------|----------|
| Impacto en Ingresos / Egresos / Neto | **Ninguno** — las tres cards operativas y el `RESUMEN` CSV se mantienen |
| Snapshot / `ingresos_operativos` | Sin cambios; las cards nuevas no participan en match/mismatch |
| Fuente de depósitos recibidos | Suma de cargos `DEPOSIT_HOLD` (registro de garantía), no allocations ni `payments.amount` |
| Fecha del rango | `charges.charge_date` entre `date_from` y `date_to` (mismo timezone `America/Tijuana`) |
| Plaza | Misma regla que ingresos: vía `unit.property.plaza_id` del contrato/unidad del cargo |
| Anulados | Excluir soft-deleted; si existe void de hold, no contar cargos anulados (mismo criterio que `DepositBalanceService` / `VoidDepositHoldAction`) |
| Ingreso bruto (caja) | `incomeTotal` (operativo) + `depositsReceivedTotal` — solo etiqueta informativa |
| Neto | No se redefine; no agregar “neto de caja” en esta fase |
| Reembolsos | Ya van en Egresos (`REEMBOLSO DEPÓSITO`); no restarlos otra vez en la card de depósitos |

### UI

- Dos `stat-card` adicionales (fila secundaria o misma grilla ampliada), **después** de Ingresos / Egresos / Neto o claramente separadas como “Caja / garantías”:
  1. **Depósitos recibidos** — `depositsReceivedTotal` + hint con conteo de holds
  2. **Ingreso bruto (con depósitos)** — `grossCashInTotal` + hint que aclare que **no** es el ingreso del cierre mensual
- Copy i18n (`lang/es|en/finance.php`): nombres que no digan solo “Ingresos” para el bruto.
- Nota operativa existente (excluye DEPOSIT_HOLD / DEPOSIT_APPLY del ingreso operativo) se mantiene.

### Servicio

Extender `CashFlowReportService::build()` (no duplicar en Livewire):

| Campo nuevo | Contenido |
|-------------|-----------|
| `depositsReceivedTotal` | Suma `DEPOSIT_HOLD.amount` en rango + plaza |
| `depositsReceivedCount` | Cantidad de holds en el rango |
| `grossCashInTotal` | `round(incomeTotal + depositsReceivedTotal, 2)` |

CSV (opcional en fase 2): filas de resumen adicionales `TOTAL_DEPOSITOS_RECIBIDOS` y `INGRESO_BRUTO_CON_DEPOSITOS` **después** del `RESUMEN` operativo, sin renombrar `TOTAL_INGRESOS` / `NETO`.

### Fuera de alcance (fase 2)

- Incluir depósitos en `incomeTotal` o en snapshot de cierre
- Detalle tabular de cada `DEPOSIT_HOLD` (puede ser fase 3)
- Neto de caja alternativo
- Cambiar `BuildMonthCloseSnapshotAction`

### Tests mínimos (fase 2)

- Hold en rango suma en `depositsReceivedTotal`; hold fuera de rango no.
- `incomeTotal` y `netTotal` idénticos con o sin holds en el periodo (regresión de congruencia).
- Con plaza: hold de otra plaza no entra; hold de la plaza sí.
- `grossCashInTotal === incomeTotal + depositsReceivedTotal`.
- Banner de snapshot sigue comparando solo operativos (sin depósitos).
