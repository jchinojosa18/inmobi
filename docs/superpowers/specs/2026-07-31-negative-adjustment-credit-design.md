# ADJUSTMENT negativo → crédito aplicado — Design Spec

**Date:** 2026-07-31  
**Status:** Approved  
**Related:** `2026-07-22-adjustment-apply-credit-design.md`, `Contracts\Show`, `ApplyCreditBalanceAction`

## Problem

Un `ADJUSTMENT` con monto negativo queda como cargo con saldo negativo en el estado de cuenta:

- `paid` se fuerza a `0` (`max(min(alloc, amount), 0)`).
- `balance = amount` (negativo) y el estatus UI cae en “Pagado” porque `balance <= 0`.
- Totales del periodo muestran `pagado > cargos` aunque `cargos − pagado = saldo` cuadre aritméticamente.
- El motor de cobranza ignora saldos negativos (`pendingAmount > 0` en `ChargeAllocationPrioritizer`), así que el descuento **no** reduce adeudo ni alimenta `credit_balances`.

Evidencia local: contrato `#3` (org DEPARTAMENTOS RUIZ) con 3 ajustes negativos (−301.25) “colgados”, header de saldo pendiente `$0`, y crédito real `$198.75` por overflow de pagos.

Esto contradice la regla de arquitectura: saldo a favor vive en `credit_balances`, no como cargo negativo huérfano.

## Goal

Un ajuste negativo es **descuento / condonación** que se aplica al momento:

1. Reduce adeudo pendiente vía el flujo de crédito existente, o
2. Si no hay pendientes, queda como saldo a favor en `credit_balances`.

El estado de cuenta debe leerse de forma coherente: sin saldos negativos “Pagados” y sin `pagado > cargos` por líneas de descuento.

## Approach (approved)

**A — Crédito + `ApplyCreditBalanceAction`**

Al registrar un `ADJUSTMENT` negativo:

1. Crear el `Charge` (auditoría / mes cerrado / activitylog).
2. Acreditar `abs(amount)` en `credit_balances`.
3. Marcar el cargo como liquidado vía crédito en `meta`.
4. Invocar `ApplyCreditBalanceAction` (aplica a pendientes por prioridad; sobrante permanece en crédito).

Descartado:

- **B** Pago sintético `method=ADJUSTMENT` — ensucia recibos/reportes.
- **C** Solo fix de UI — deja crédito fantasma en BD.

Supersede parcial de `2026-07-22-adjustment-apply-credit-design.md`: ahí el negativo era no-op del apply-credit; ahora el negativo **genera** crédito y luego aplica.

## Behavior

### Crear ajuste (`contracts/{id}`)

| Monto | Flujo |
|---|---|
| Positivo (`> 0`) | Sin cambio de negocio: crear cargo + `ApplyCreditBalanceAction` (consume crédito existente hacia el ajuste y otros pendientes). |
| Negativo (`< 0`) | Crear cargo → acreditar `abs(amount)` → marcar settled → `ApplyCreditBalanceAction`. |
| Cero | Sigue inválido (`not_in:0`). |

### Action nueva

Extraer la lógica de `Show::createAdjustment` a algo como:

`App\Actions\Charges\RegisterContractAdjustmentAction`

Responsabilidades:

- Validar monto ≠ 0 (Livewire sigue validando inputs de formulario).
- Crear `Charge` `TYPE_ADJUSTMENT` con `meta.reason` (obligatorio), `comment`, `linked_to`, `created_from`, `created_by_user_id` (igual que hoy).
- Respetar `MonthCloseGuard` (misma excepción de ajuste con razón).
- Si `amount < 0`:
  - Incrementar `credit_balances.balance` en `abs(amount)` (crear/restaurar fila si hace falta; patrón similar a `ApplyPaymentAction::registerCredit`, sin `Payment`).
  - `meta` del crédito: `last_source = adjustment_credit`, `last_amount`, `source_charge_id`.
  - En el cargo: `meta.settled_as_credit = true`, `meta.credit_amount = abs(amount)`.
  - Luego `ApplyCreditBalanceAction`.
- Si `amount > 0`: solo `ApplyCreditBalanceAction` (comportamiento actual).
- Toda la secuencia en transacción DB.

`Show::createAdjustment` queda como orquestación UI → Action → flash/errores.

### Estado de cuenta (`Contracts\Show` ledger)

Para `type = ADJUSTMENT` con `amount < 0` y `meta.settled_as_credit = true`:

| Campo UI | Valor |
|---|---|
| Monto | `amount` (negativo, visible) |
| Pagado | `amount` (mismo valor negativo; el descuento ya se “aplicó” vía crédito) |
| Saldo | `0.00` |
| Estatus | Nueva etiqueta “Aplicado” (tono info o success) |

Misma idea que depósito (`paid = amount`, `balance = 0`), pero el monto es negativo. Así cada fila cumple `amount − paid = balance` y los totales del periodo también:

- `charges_total` incluye el negativo.
- `paid_total` incluye el mismo negativo (no queda `pagado > cargos`).
- `balance_total` no arrastra saldos negativos huérfanos.
- Header `pendingBalance = max(0, sum(balance))` — sin cambio de fórmula; el crédito usable vive en `credit_balances`.

Ajustes negativos **históricos no settled** (antes del backfill): la UI puede seguir mostrando el síntoma hasta correr el backfill; no se inventa una segunda semántica permanente.

### Backfill

Comando artisan idempotente (nombre tentativo: `inmo:adjustments:settle-negative-credits`):

1. Seleccionar `charges` con `type = ADJUSTMENT`, `amount < 0`, `deleted_at IS NULL`, sin `meta.settled_as_credit`.
2. Por cada cargo (orden `id` asc, por contrato con lock):
   - Acreditar `abs(amount)` en `credit_balances` del contrato.
   - Marcar `settled_as_credit` + `credit_amount` en `meta`.
   - `ApplyCreditBalanceAction` para ese contrato.
3. Re-ejecutar no debe volver a acreditar (guard por `settled_as_credit`).

Caso contrato `#3` (estado al diseñar): crédito pasa de `198.75` a `198.75 + 301.25 = 500.00` (sin pendientes positivos); filas negativas quedan saldo `0` / “Aplicado”.

### Reportes / ingresos

- El cargo negativo **no** recibe `PaymentAllocation`.
- El crédito aplicado genera `Payment method=CREDIT` + allocations a cargos positivos (RENT/PENALTY/…); ingresos operativos siguen siendo suma de allocations — sin cambio de definición.
- No se crea `Payment` sintético solo por el descuento.

## Out of Scope

- Cambiar prioridad de `ChargeAllocationPrioritizer`.
- Reescribir o anular ajustes positivos.
- UI de edición/borrado de ajustes.
- Cambios al reporte de flujo más allá del efecto natural del crédito aplicado.
- Migración masiva de otros tipos de cargo negativo (solo `ADJUSTMENT`).

## Implementation sketch

| Pieza | Cambio |
|---|---|
| `RegisterContractAdjustmentAction` | Nueva; create + credit-on-negative + apply credit |
| `Contracts\Show::createAdjustment` | Delegar a la Action |
| `Contracts\Show::mapChargeToLedgerRow` + status | Rama settled negative adjustment |
| `lang/{es,en}/contracts.php` | `charge_statuses.applied` (o clave equivalente) |
| Comando backfill | Idempotente |
| `docs/AI_ONBOARDING.md` §4.2 / §4.5 | Documentar semántica negativo → crédito |
| Tests Feature | Ver Acceptance |

## Acceptance Criteria

- [ ] Ajuste negativo sin cargos pendientes → `credit_balances` sube `abs(amount)`; cargo con `settled_as_credit`; fila UI saldo `0` / “Aplicado”.
- [ ] Ajuste negativo con renta pendiente → se aplica crédito a la renta (y otros por prioridad); residual de crédito correcto.
- [ ] Ajuste positivo: sin regresión (sigue pudiendo consumir crédito existente).
- [ ] Totales de periodo con descuento settled: fila con `paid = amount`, `balance = 0`; no queda `pagado > cargos` ni saldo negativo huérfano.
- [ ] Backfill idempotente: segunda corrida no duplica crédito.
- [ ] `./vendor/bin/sail test --filter=<tests>` + `./vendor/bin/sail pint --dirty`.

## Docs

- Actualizar `docs/AI_ONBOARDING.md`: invocaciones de crédito y regla “ADJUSTMENT negativo → credit_balances + apply”.
- Nota en `docs/ARCHITECTURE.md` (ajustes +/-) si el párrafo actual implica que el negativo vive solo como cargo.
