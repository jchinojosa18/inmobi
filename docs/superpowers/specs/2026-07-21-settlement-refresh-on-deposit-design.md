# Finiquito — refresh al registrar/anular depósito — Design Spec

**Date:** 2026-07-21  
**Status:** Approved  
**Related:** `app/Livewire/Contracts/SettlementWizard.php`, `app/Livewire/Contracts/DepositHoldForm.php`, `docs/superpowers/specs/2026-07-21-deposit-hold-accordion-design.md`

## Goal

En `contracts/{id}`, el card **Finiquito de contrato** debe actualizar su resumen de depósito (pagado / aplicado / reembolsado / disponible / adeudo) en cuanto se registra o se anula un `DEPOSIT_HOLD`, sin recargar la página.

## Problem

`DepositHoldForm` ya despacha `deposit-hold-registered` y `deposit-hold-voided`. `Contracts\Show` los escucha, pero `SettlementWizard` es un componente Livewire hermano y **no** se re-renderiza. Sus totales salen de `DepositBalanceService` en `render()`, así que quedan stale hasta un refresh o una interacción propia del wizard.

## Out of Scope

- Cambios en `ProcessContractSettlementAction` o reglas de finiquito
- Cambios en `DepositBalanceService` / registro / anulación de depósitos
- Remount del wizard vía `wire:key` (perdería estado del formulario)
- Refresco de otros cards no afectados

## Behavior

| Evento | Efecto en Finiquito |
|--------|---------------------|
| `deposit-hold-registered` | Re-render → totales actualizados |
| `deposit-hold-voided` | Re-render → totales actualizados |

El estado del formulario del wizard (`move_out_date`, `concepts`, `evidenceFiles`, último PDF/summary) **no** se resetea: solo se vuelve a ejecutar `render()`.

## Architecture

### Enfoque elegido

En `SettlementWizard`, mismo patrón que `Show`:

```php
#[On('deposit-hold-registered')]
#[On('deposit-hold-voided')]
public function onDepositHoldChanged(): void {}
```

Livewire re-ejecuta el request/render del componente al recibir el evento. Los valores `paidDeposit`, `availableDeposit`, etc. se recalculan con `DepositBalanceService` como hoy.

### Alternativas descartadas

| Enfoque | Motivo |
|---------|--------|
| `wire:key` remount desde Show | Pierde conceptos/fecha/archivos en edición |
| Props de totales desde Show | Acoplamiento innecesario; el wizard ya calcula en `render()` |

## Testing

Feature test (Livewire) que monte la página de contrato o el wizard junto al flujo de depósito:

1. Contrato con `deposit_amount` y sin holds → finiquito muestra paid `$0.00` (o equivalente).
2. Registrar depósito (vía `DepositHoldForm` o dispatch del evento tras crear charge) → finiquito `assertSee` del monto registrado en `deposit_paid` / available.
3. Idealmente: anular → vuelve a reflejar el monto reducido.

Si el test monta solo `SettlementWizard`, basta con:

- Crear hold en DB
- `Livewire::test(SettlementWizard::class, ...)->dispatch('deposit-hold-registered')->assertSee(...)`

(y análogo con `deposit-hold-voided` tras soft-delete / void).

## Verification

```bash
./vendor/bin/sail test --filter=Settlement
./vendor/bin/sail pint --dirty
```

(Ajustar filter al nombre del test nuevo si es más específico.)

## Risks / Notes

- Eventos son globales en la página del contrato; listeners vacíos son intencionales (solo disparan re-render).
- No hay race con el Action: el dispatch ocurre después del commit en `DepositHoldForm`.
