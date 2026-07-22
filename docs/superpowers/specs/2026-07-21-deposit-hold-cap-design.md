# Tope de depósito recibido (DEPOSIT_HOLD) — Design Spec

**Date:** 2026-07-21  
**Status:** Approved  
**Related:** `docs/AI_ONBOARDING.md` §4.3, `RegisterDepositHoldAction`, `DepositHoldForm`, `DepositBalanceService`

## Goal

En el detalle de contrato (`contracts/{id}`), el depósito recibido se registra como uno o más cargos `DEPOSIT_HOLD`, pero la suma de esos cargos **no puede superar** `contracts.deposit_amount`. Cuando el cupo está completo, no se permiten más registros y la UI deja de mostrar el formulario.

## Out of Scope (v1)

- Cambiar finiquito (`ProcessContractSettlementAction`) ni la definición de depósito *disponible* (`availableDepositAmount`, basada en allocations pagadas).
- Soft-delete / anulación de `DEPOSIT_HOLD` para reabrir cupo.
- Constraint único en DB (se permiten varios parciales).
- Migración o corrección automática de contratos históricos cuya suma de `DEPOSIT_HOLD` ya exceda `deposit_amount` (solo se bloquean registros nuevos).
- Permisos nuevos (seguir con `charges.manage`).

## User Stories

1. Como usuario con `charges.manage`, quiero registrar el depósito en parciales hasta completar el monto del contrato.
2. Como usuario con `charges.manage`, no debo poder registrar un monto que haga que la suma de `DEPOSIT_HOLD` supere `deposit_amount`.
3. Como usuario con `charges.manage`, cuando el depósito ya está completo, quiero ver un estado claro y no el formulario de alta.

## Architecture

### Enfoque elegido

Guard de negocio en `RegisterDepositHoldAction` (con `lockForUpdate` del contrato) + helpers de saldo registrado/remanente reutilizados por Livewire + UI que oculta el form al completar.

### Alternativas descartadas

| Enfoque | Motivo de descarte |
|---------|-------------------|
| Solo UI | No protege Action / smoke / doble submit |
| Un solo `DEPOSIT_HOLD` por contrato (unique) | Bloquearía parciales acordados |

## Business Rules

Definiciones (fuente de verdad para el tope de *registro*):

| Concepto | Cálculo |
|----------|---------|
| Registrado | `SUM(charges.amount)` donde `type = DEPOSIT_HOLD` y `contract_id` (soft-deletes excluidos vía modelo) |
| Remanente | `max(contract.deposit_amount - registrado, 0)` |
| Completo | `remanente = 0` |

Al ejecutar `RegisterDepositHoldAction`:

1. Lock del contrato (`lockForUpdate`), como hoy.
2. Calcular registrado/remanente.
3. Si `remanente = 0` → `ValidationException` (depósito ya completo).
4. Si `amount > remanente` → `ValidationException` (excede remanente).
5. Si `amount <= 0` → error existente.
6. Idempotencia existente: mismo `charge_date` + mismo `amount` reutiliza el cargo existente **solo si** ese registro ya está dentro del historial (no crea duplicado); no se usa para saltar el tope.
7. Crear `DEPOSIT_HOLD` con meta actual (`subtype=RECEIVED`, notes, etc.).

Notas:

- El contador del tope usa **montos de cargo**, no allocations. El pago del `DEPOSIT_HOLD` (recibo/evidencia) sigue siendo flujo aparte.
- Contratos con `deposit_amount = 0`: remanente 0 → no se puede registrar depósito (consistente con “completo”).

## Components

### `DepositBalanceService` (o métodos equivalentes)

Agregar:

- `registeredDepositHoldAmount(Contract $contract): float`
- `remainingDepositHoldAmount(Contract $contract): float`

Usados por Action y por `DepositHoldForm` para evitar duplicar queries.

### `RegisterDepositHoldAction`

Validar tope dentro de la transacción tras el lock. Mensajes vía claves i18n en `lang/es/contracts.php` (y `en` si existe el par).

### `DepositHoldForm` + Blade

- Mostrar: depósito del contrato · registrado · remanente.
- Prefill de monto = remanente (no el total del contrato si ya hay parciales).
- Si remanente > 0: formulario actual.
- Si remanente = 0: ocultar form; mostrar estado “Depósito completo” (resumen registrado / monto contrato).
- Tras `deposit-hold-registered`, re-render con totales actualizados (evento ya despachado).

## Testing

Con `./vendor/bin/sail test`:

**Unit / Action**

- Parciales sucesivos hasta completar → OK.
- Monto que supera remanente → ValidationException.
- Contrato ya completo → ValidationException.
- Idempotencia misma fecha+monto sigue sin crear duplicado cuando aplica.

**Feature Livewire**

- Form visible y prefill = remanente cuando hay cupo.
- Form oculto / estado completo cuando remanente = 0.

## Docs touchpoints (post-implementación)

- Actualizar `docs/AI_ONBOARDING.md` §4.3 con la regla de tope por suma de `DEPOSIT_HOLD` vs `deposit_amount`.
- Mencionar en `README` / `ARCHITECTURE` solo si ya documentan el registro de depósito en una línea (diff mínimo).

## Acceptance Criteria

- [ ] No se puede crear un `DEPOSIT_HOLD` que haga `SUM(DEPOSIT_HOLD) > deposit_amount`.
- [ ] Se permiten varios `DEPOSIT_HOLD` mientras la suma sea ≤ `deposit_amount`.
- [ ] UI en `contracts/{id}` oculta el form cuando el cupo está completo y muestra estado claro.
- [ ] Prefill del monto = remanente.
- [ ] Tests Sail verdes para Action + Livewire; Pint dirty limpio.
