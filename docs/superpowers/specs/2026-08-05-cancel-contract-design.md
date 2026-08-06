# Cancel contract (wrong tenant / mistaken create) — Design Spec

**Date:** 2026-08-05  
**Status:** Approved (brainstorming)  
**Related:** `Contract`, `VoidDepositHoldAction`, `MonthCloseGuard`, `AuditLogger`, create/edit lock de unit/tenant

## Problem

Al crear un contrato, el usuario puede equivocarse de inquilino. En edit, `tenant_id` y `unit_id` están bloqueados a propósito (ver [contract-unit-select-occupied](2026-07-22-contract-unit-select-occupied-design.md)). No existe forma de eliminar o anular el contrato, así que la unidad queda ocupada y el historial apunta al inquilino incorrecto.

Hard delete o editar inquilino romperían auditoría, kardex, PDFs y el ledger.

## Goal

Permitir **anular** un contrato creado por error cuando aún no hay movimiento financiero irreversible, liberar la unidad, y guiar al usuario cuando no sea seguro anular.

## Decisions

| Tema | Decisión |
|---|---|
| Mecanismo | Soft cancel: nuevo `status = cancelled` (no hard delete; no editar tenant) |
| Distinto de finiquito | `ended` = finiquito / renovación; `cancelled` = error de captura |
| Contrato limpio | Anular en una transacción con motivo obligatorio |
| Contrato con movimientos | Bloquear + atajos guiados (sin auto-revertir pagos/depósitos) |
| Mes cerrado | Si anular implica mutar cargos de mes cerrado → bloqueo total (sin bypass Admin) |
| Motivo | Obligatorio; auditoría + `meta` |
| Permiso | `contracts.manage` (sin permiso nuevo en v1) |

## Out of scope

- Editar `tenant_id` / `unit_id` en contratos existentes
- Hard delete de filas de contrato
- Auto-anular pagos, allocations o depósitos en cascada
- Excepción Admin para mutar meses cerrados
- Reabrir / “des-anular” contrato (v1)

## Behavior

### Contrato anulable (“limpio”)

Debe cumplir **todas**:

1. `status = active` (incluye vencido visual: `ends_at` pasado pero aún `active`)
2. Sin `Payment` activo del contrato (`contract_id`; ignorar soft-deleted si aplica)
3. Sin `DEPOSIT_HOLD` vigente (no se auto-anula depósito en este flujo)
4. Sin `PaymentAllocation` sobre cargos del contrato
5. Sin saldo a favor (`credit_balances.amount` ≤ 0 o inexistente)
6. Todo cargo que haya que limpiar cae en mes **abierto** (`MonthCloseGuard`)
7. No es origen de una renovación ya aplicada (`meta.renewed_to_contract_id` ausente)
8. Motivo no vacío (trim; máx. razonable p. ej. 500 chars en validación)

### Efectos al anular

En transacción:

1. Lock del contrato
2. Eliminar cargos abiertos elegibles del contrato (pendientes, sin allocations, mes abierto): RENT / PENALTY / SERVICE / ADJUSTMENT (mismo espíritu que void de depósito: delete + audit, no cargo negativo)
3. Actualizar contrato:
   - `status = cancelled`
   - `active_lock = null` (libera unidad para nuevo contrato `active`)
   - `meta.cancelled_at` (ISO)
   - `meta.cancellation_reason` (string)
   - `meta.cancelled_by_user_id`
4. `AuditLogger` action `contract.cancelled` con motivo y resumen de cargos eliminados
5. Documentos PDF existentes se **conservan** (historial)

### Contrato no anulable

No ejecuta la anulación. La UI muestra bloqueos concretos + atajos:

| Condición | Atajo / mensaje |
|---|---|
| Pagos / allocations / cargos parciales o pagados | Enlace a cobranza / flujo de pagos del contrato |
| `DEPOSIT_HOLD` vigente | Enlace/ancla al panel de depósito (anular depósito existente) |
| Saldo a favor | Mensaje: regularizar crédito antes (sin auto-clear en v1) |
| Mes cerrado en cargos a limpiar | Solo explicación; sin bypass |
| Ya `ended` / `cancelled` | No mostrar botón |
| Ya renovado a otro contrato | Bloqueo: no anular origen de renovación |

Tras resolver bloqueos (p. ej. void depósito, sin pagos), el usuario reintenta anular.

### Post-anulación operativa

Flujo feliz del caso “inquilino incorrecto”:

1. Anular contrato erróneo (limpio)
2. Crear contrato nuevo con inquilino correcto en la misma unidad

## Data model

- `contracts.status`: valor nuevo `cancelled` (string; sin valores enum DB rígidos hoy — constante en modelo)
- `Contract::STATUS_CANCELLED = 'cancelled'`
- `active_lock`: null cuando no `active` (hook existente en modelo)
- `meta` keys: `cancelled_at`, `cancellation_reason`, `cancelled_by_user_id`

No se requiere migración de columnas nuevas si `status` y `meta` (JSON) ya soportan esto. Verificar índices/filtros UI únicamente.

## Architecture

### Action

`App\Actions\Contracts\CancelContractAction`

```
execute(Contract $contract, string $reason, ?int $userId): void
```

- Validación → `ValidationException` con mensajes por campo/clave (`cancel`, `reason`, etc.)
- Elegibilidad centralizada (idealmente método/query reusable para UI “¿se puede anular?” + lista de bloqueos)
- DB transaction + `lockForUpdate`
- Respeta `MonthCloseGuard` al borrar cargos
- No llama finiquito ni renovación

Opcional de soporte: `CancelContractEligibility` / método en Action que devuelva `{ allowed: bool, blockers: list }` para el modal Livewire sin duplicar reglas.

### Livewire / UI

- Detalle: [`Contracts\Show`](../../../app/Livewire/Contracts/Show.php)
  - Botón “Anular contrato” si `contracts.manage` && `status === active`
  - Modal confirmación con `x-ui.confirm-modal` (no `wire:confirm` / `window.confirm`)
  - Textarea motivo obligatorio
  - Si no elegible: lista de blockers + links; botón confirmar deshabilitado o flujo “solo ver blockers”
- Listado: [`Contracts\Index`](../../../app/Livewire/Contracts/Index.php)
  - Filtro / tab para `cancelled` (label “Anulados”), separado de `ended` (“Finalizados”)
  - Stat card / badge de estado distingue anulado vs finalizado
- i18n: `lang/es|en/contracts.php` (botón, modal, validaciones, flash, filtro)

### Schedulers / side effects

- Generación de rentas y multas ya filtran `status = active` → `cancelled` no genera cargos nuevos
- Renovación: no ofrecer renew en `cancelled`
- Finiquito: no ofrecer settle en `cancelled`
- Create contrato: unidad libre porque `active_lock` null y no hay otro `active`

## Error handling

- Motivo vacío → validación en Livewire + Action
- Race: otro usuario registra pago entre abrir modal y confirmar → Action revalida bajo lock y falla con mensaje claro
- Mes cerrado detectado al borrar cargo → ValidationException, rollback completo
- 403 si falta `contracts.manage`

## Testing

### Unit — `CancelContractActionTest`

- Anula contrato limpio con solo RENT pendiente del mes abierto; status `cancelled`; unidad libre; motivo en meta; audit
- Bloquea con payment
- Bloquea con DEPOSIT_HOLD
- Bloquea con credit balance > 0
- Bloquea si cargo a limpiar está en mes cerrado
- Bloquea si `renewed_to_contract_id` presente
- Bloquea motivo vacío
- Bloquea si ya `ended` / `cancelled`

### Feature — Show / Index

- Botón visible con `contracts.manage` en active
- Modal exige motivo
- Éxito: flash + estado anulado
- 403 / sin botón para Lectura
- Filtro listado muestra anulados
- Contrato no elegible muestra blockers + no cambia status

## UX copy (es, orientación)

- Botón: “Anular contrato”
- Título modal: “Anular contrato”
- Ayuda: explica que libera la unidad y que no es un finiquito
- Motivo: label + placeholder (ej. “Inquilino incorrecto”)
- Flash éxito: “Contrato anulado. Ya puedes crear uno nuevo en la unidad.”

## Approach rejected

1. **Editar inquilino** — inconsistencia en PDFs, kardex, documentos y cargos ya emitidos
2. **Hard delete** — pierde auditoría; choca con documentos, closures y FKs
3. **Auto-revertir ledger** — inseguro con mes cerrado, folios e ingresos reportados
