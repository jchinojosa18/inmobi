# Ajuste + saldo a favor — Design Spec

## Problem

Al registrar un `ADJUSTMENT` desde `contracts/{id}`, el saldo a favor no se consume. El crédito sí se aplica tras renta, multa, pago y finiquito.

## Approach (approved)

Tras crear el `Charge` tipo `ADJUSTMENT` en `Show::createAdjustment`, invocar `ApplyCreditBalanceAction` (mismo patrón que renta/multa).

Descartado: observer en `Charge` (demasiado mágico); Action nueva (overkill para un call).

## Behavior

- Ajuste positivo + crédito → Payment `CREDIT` + allocations al ajuste (y otros pendientes por prioridad).
- Ajuste negativo o crédito 0 → no-op (action existente).
- Sin cambios a UI, validaciones ni `MonthCloseGuard`.

## Docs

Actualizar lista de invocaciones en `docs/AI_ONBOARDING.md` §4.2.
