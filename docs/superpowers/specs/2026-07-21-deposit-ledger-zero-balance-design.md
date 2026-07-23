# Depósito en estado de cuenta con saldo $0 — Design Spec

**Date:** 2026-07-21  
**Status:** Approved  
**Related:** `2026-07-21-deposit-hold-void-pending-design.md`, `Contracts\Show`

## Goal

En el Estado de cuenta, las filas de depósito no deben parecer adeudo. El saldo de la fila debe ser **$0**.

## Behavior

- `DEPOSIT_HOLD` y `DEPOSIT_APPLY` siguen visibles con estatus **Garantía**.
- En la fila: `paid = amount`, `balance = 0`.
- Totales de periodo y saldo pendiente del header: sin cambio (siguen excluyendo tipos de depósito).

## Out of Scope

- Quitar depósitos de la tabla.
- Cambiar finiquito / `DepositBalanceService`.

## Implementation

- `Contracts\Show::mapChargeToLedgerRow`: si `isDepositLedgerType`, forzar `paid = amount` y `balance = 0`.
- Test en `ContractShowDepositPendingTest` (o Livewire) que aserte saldo `$0.00` / `balance = 0` en la fila de depósito.

## Acceptance Criteria

- [x] Fila `DEPOSIT_HOLD` muestra saldo 0 y pagado = monto.
- [x] Saldo pendiente del contrato no incluye el depósito.
- [x] Tests Sail + Pint.
