# Depósito independiente con comprobante propio — Design Spec

**Date:** 2026-07-21  
**Status:** Approved (option C)  
**Related:** `RegisterDepositHoldAction`, `VoidDepositHoldAction`, `DepositBalanceService`

## Goal

El depósito es garantía recibida, no un pago de cobranza. Al registrar: cargo `DEPOSIT_HOLD` + folio `DEP-YYYY-#####` + PDF + evidencia documental. Finiquito usa lo registrado.

## Behavior

- No crea `Payment` / allocation de cobranza.
- Folio en `meta.deposit_receipt_folio`; PDF `/deposits/{chargeId}/receipt.pdf`.
- Evidencia: `Documents\Panel` sobre el `Charge`.
- `availableDepositAmount` = registrado − aplicado − reembolsado.
- Anular: soft-delete del cargo; limpia pagos legacy `meta.source=deposit_hold` si existían; bloquea allocations ajenas.

## Out of Scope

- Commit/push hasta prueba del usuario.
- Serie de folio configurable en settings (v1 fija `DEP-YYYY-`).
