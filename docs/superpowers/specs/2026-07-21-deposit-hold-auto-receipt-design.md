# Depósito = recibido con recibo al registrar — Design Spec

**Date:** 2026-07-21  
**Status:** Approved (option A)  
**Related:** `RegisterDepositHoldAction`, `VoidDepositHoldAction`, `DepositBalanceService`

## Goal

Al registrar un depósito, queda **recibido** en el mismo paso: cargo `DEPOSIT_HOLD` + `Payment` con folio + allocation directa. No hace falta un segundo pago de cobranza.

## Behavior

- `RegisterDepositHoldAction` crea cargo, pago (`meta.source=deposit_hold`) y allocation solo a ese cargo (no usa `ApplyPaymentAction`).
- Método: efectivo o transferencia (UI).
- `VoidDepositHoldAction` anula cargo + pago deposit-sourced; bloquea pagos ajenos o contrato finiquitado.
- Depósitos históricos sin pago deposit-sourced siguen anulables si no tienen allocations, o bloqueados si tienen pago manual ajeno.

## Out of Scope

- Tabla `Deposit` separada.
- Backfill de históricos.
- Commit/push (usuario prueba en local primero).
