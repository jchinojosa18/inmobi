# Cargos de renta 5 días antes del vencimiento — Design Spec

**Date:** 2026-07-22  
**Status:** Approved (approach 1 + create/activate option B + catch-up A)  
**Related:** `GenerateMonthlyRentChargesAction`, `GenerateRentChargesCommand`, `routes/console.php`, `Contract` model hooks

## Goal

Generar cargos `RENT` automáticamente **5 días antes** de su `due_date` (timezone `America/Tijuana`), con catch-up diario si el job se atrasa. Al crear/activar un contrato, seguir generando de inmediato el RENT del mes corriente.

## Decisions

| Tema | Decisión |
|------|----------|
| Ventana | `asOf >= due_date - 5 días` |
| Crear/activar contrato | Generar mes corriente de inmediato (opción B) |
| Cruce de mes | Sí: due 1-ago → generar desde 27-jul con `period=2026-08` |
| Catch-up | Diario: crear todo pendiente cuya ventana ya abrió o ya venció |
| Enfoque | Scheduler diario + modo due-soon en el action/comando |
| Backfill manual | `--month=YYYY-MM` sigue generando el mes completo sin filtro de ventana |
| `charge_date` | Sigue siendo inicio del periodo (`YYYY-MM-01`), no la fecha de generación |

## Behavior

### Regla de generación (due-soon)

Para un contrato `active` y una fecha `asOf` (default: hoy Tijuana):

1. Calcular candidatos de periodo acotados a **mes anterior, mes de `asOf` y mes siguiente** (cubre cruce de mes + catch-up corto si el job se atrasó unos días).
2. Para cada periodo candidato, calcular `due_date` con `due_day` (clamp al último día del mes, igual que hoy).
3. Crear el cargo solo si:
   - el contrato está vigente en ese periodo (`starts_at` / `ends_at`);
   - `asOf >= due_date - 5 días`;
   - no existe ya un RENT para `(contract_id, period)`;
   - el mes del cargo no está cerrado (`MonthCloseGuard` en create de `Charge`).
4. Tras crear/asegurar: aplicar crédito con `ApplyCreditBalanceAction` (comportamiento actual).
5. Idempotencia: unique `charges_contract_rent_period_key_unique` + catch de race (sin cambio).

Huecos más antiguos que el mes anterior se cubren con backfill manual `--month=YYYY-MM`, no con el job diario.

### Ejemplos

| due_day | due_date | generar desde | `period` |
|--------|----------|---------------|----------|
| 1 | 2026-08-01 | 2026-07-27 | `2026-08` |
| 15 | 2026-08-15 | 2026-08-10 | `2026-08` |
| 31 | 2026-02-28 (feb) | 2026-02-23 | `2026-02` |

### Crear / activar contrato

Sin cambio de semántica: `ensureCurrentMonthForContract` crea el RENT del mes corriente al crear un contrato `active` o al pasar a `active`, sin esperar la ventana de 5 días.

### Sync de `due_day` / `grace_days`

Sin cambio: `syncOpenRentScheduleForContract` recalcula `due_date` / `grace_until` en rentas de meses abiertos.

## Architecture

### `GenerateMonthlyRentChargesAction`

- Nuevo: `executeDueSoon(?CarbonImmutable $asOf = null, ?int $organizationId = null): array{created:int, skipped:int, as_of:string}`  
  — modo diario con filtro de ventana y candidatos de periodo (mes anterior + actual + siguiente).
- Mantener: `execute` / `executeForOrganization($month, …)` — backfill forzado del mes completo (Dashboard, palette, smoke, `--month`).
- Mantener: `ensureCurrentMonthForContract`, `syncOpenRentScheduleForContract`, `createRentChargeForContractPeriod`.

### `inmo:generate-rent`

- Sin `--month` → `executeDueSoon()` (modo scheduler).
- Con `--month=YYYY-MM` → `execute($month)` (backfill mes completo).
- Lock/mutex existente sin cambio.

### Scheduler (`routes/console.php`)

- De `monthlyOn(1, '00:10')` → **diario** `00:10` America/Tijuana.
- Invocar `inmo:generate-rent` **sin** `--month` (due-soon).

### UI operativa

- Dashboard / Command Palette “generar rentas del mes”: sigue llamando `executeForOrganization` del mes corriente (override sin filtro de ventana).

## Out of Scope

- Cambiar semántica de create/activate (opción C).
- Notificaciones/recordatorios al inquilino.
- Cambiar `charge_date` a la fecha de generación.
- Migraciones / backfill masivo de cargos históricos faltantes (el catch-up diario cubre a partir del deploy).
- Commit/push de implementación (se hace en fase de plan/ejecución).

## Docs to update

- `docs/AI_ONBOARDING.md`: scheduler diario + regla −5 días + modos del comando.

## Test plan

- Unit `GenerateMonthlyRentChargesAction`:
  - `due_day=1`, `asOf=2026-07-27` → crea RENT `2026-08`.
  - `due_day=15`, `asOf=2026-08-09` → no crea; `asOf=2026-08-10` → crea `2026-08`.
  - Catch-up: `asOf` posterior a la ventana (p. ej. 28-jul / 1-ago) sigue creando el pendiente.
  - No duplicar si el cargo ya existe.
  - Contrato no vigente en el periodo → skip.
- Feature comando:
  - Sin `--month` → due-soon.
  - Con `--month` → backfill mes completo.
- Feature autogeneración al crear contrato: sin regresión (sigue creando mes actual).
- Schedule: diario `00:10`, no `monthlyOn(1)`.
