# Anular depósito + excluir de saldo pendiente — Design Spec

**Date:** 2026-07-21  
**Status:** Approved  
**Related:** `docs/superpowers/specs/2026-07-21-deposit-hold-cap-design.md`, `Contracts\Show`, `DepositHoldForm`

## Goal

1. El depósito (`DEPOSIT_HOLD`) no debe inflar el **Saldo pendiente** ni los totales de adeudo en el detalle de contrato.
2. Permitir **anular** un depósito mal capturado (contrato/unidad equivocada u otro error), liberando el cupo del tope.

## Out of Scope

- Cambiar el modelo ledger (sigue existiendo `DEPOSIT_HOLD` como cargo pasivo).
- Anular o reescribir pagos/recibos ligados.
- Mover un depósito de un contrato a otro.
- UI de anulación en cobranza / otros módulos.

## Business Rules

### Saldo pendiente (estado de cuenta)

- En `Contracts\Show`, excluir `DEPOSIT_HOLD` y `DEPOSIT_APPLY` al calcular:
  - Saldo pendiente
  - Totales de periodo (cargos / pagado / saldo)
  - Cards superiores de cargos acumulados / aplicado (alineado a adeudo operativo)
- El renglón `DEPOSIT_HOLD` **sigue visible** en la tabla.
- Estatus UI para `DEPOSIT_HOLD`: **Garantía** (no Pendiente/Parcial/Pagado por allocations).

### Anular depósito

- Soft-delete del `Charge` tipo `DEPOSIT_HOLD`.
- Permitido solo si:
  - No tiene `PaymentAllocation` (ni pago/recibo ligado).
  - Contrato no está `ended` / sin finiquito (`settlement_batch_id` en meta o status ended).
  - MonthCloseGuard no bloquea el delete del mes del cargo.
- Permiso: `charges.manage`.
- Audit: `deposit.hold.void`.
- Tras anular, `registeredDepositHoldAmount` / remanente se recalculan solos (Eloquent SoftDeletes).

## Architecture

- `VoidDepositHoldAction` (transaccional, lock contrato + cargo).
- `DepositHoldForm`: lista de holds activos del contrato + botón Anular con confirmación.
- `Contracts\Show`: filtrar tipos de depósito en totales; mapear estatus Garantía.

## Testing

- Show: con RENT pagada + DEPOSIT_HOLD sin pago → saldo pendiente = 0 (no incluye depósito).
- Void: anular hold sin allocations → soft-deleted; remanente aumenta.
- Void: hold con allocation → ValidationException.
- Void: contrato ended → bloqueado.

## Acceptance Criteria

- [ ] Saldo pendiente del contrato no incluye `DEPOSIT_HOLD` / `DEPOSIT_APPLY`.
- [ ] Fila de depósito muestra estatus Garantía.
- [ ] Se puede anular un depósito sin pago/finiquito.
- [ ] No se puede anular si ya tiene pago o contrato finiquitado.
- [ ] Tests Sail + Pint; commit + push a `origin/main`.
