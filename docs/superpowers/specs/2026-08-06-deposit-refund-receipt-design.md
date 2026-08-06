# Recibo de devolución de depósito — Design Spec

**Date:** 2026-08-06  
**Status:** Approved  
**Related:** finiquito surplus visibility, `ProcessContractSettlementAction`, `REEMBOLSO DEPÓSITO`, Documents panel on contract show

## Goal

Cuando el finiquito genera devolución (`depositRefund > 0`), emitir un **recibo PDF de devolución** con folio propio, **guardarlo como `Document` del contrato**, y exponer un link en el panel Finiquito junto al sobrante.

## Decisions (locked)

| Tema | Elección |
|------|----------|
| Alcance UX | Opción **B**: PDF propio + Documentos + link en Finiquito |
| Enfoque técnico | Generar en el Action de finiquito (opción **1**) |
| Contratos viejos | Solo **hacia adelante** (sin backfill #100) |

## Out of Scope

- Backfill / regeneración para finiquitos ya cerrados
- Email o WhatsApp del recibo
- Cambiar el PDF on-the-fly de finiquito (`contracts.settlements.pdf`)
- Flujo “marcar devolución como pagada en efectivo” aparte del gasto
- Nueva pantalla de detalle de expense

## User Stories

1. Al finiquitar con sobrante, el sistema crea un recibo `DEV-…` y lo deja en Documentos del contrato.
2. En el panel Finiquito de un contrato ended con devolución, abro **Ver recibo de devolución** y veo el PDF.
3. Si no hubo devolución, no se crea Document ni link de recibo.

## Behavior

### Trigger

Dentro de `ProcessContractSettlementAction`, en la misma transacción DB, **después** de crear el `Expense` `REEMBOLSO DEPÓSITO` cuando `$depositRefund > 0`:

1. Generar folio `DEV-{Y}-{#####}` (padding 5, secuencia por `organization_id`, incluir soft-deleted docs que ya tengan ese folio en meta si aplica; espejo de `GenerateDepositReceiptFolioAction`).
2. Generar PDF DomPDF.
3. `Document::storeNew` ligado al **contrato**.
4. Persistir en `meta.settlements.{batchId}`:
   - `refund_receipt_folio`
   - `refund_receipt_document_id`

Si `depositRefund === 0`, no hay folio, PDF ni Document.

### PDF content (mínimo)

- Título: Recibo de devolución de depósito  
- Folio `DEV-…`  
- Fecha (move-out / spent_at del expense)  
- Inquilino, propiedad/unidad  
- Contrato `#id`  
- Depósito disponible al finiquitar  
- Depósito aplicado  
- Monto devolución (total del expense)  
- Si `credit_refunded > 0`: desglose depósito vs saldo a favor  
- Nota breve: comprobante de devolución por finiquito (no es ingreso operativo)

### Document storage

| Campo | Valor |
|-------|--------|
| `documentable` | `Contract` |
| `type` | p.ej. `CONTRACT_DOCUMENT` (mismo patrón lease) |
| `category` | `ContractDocumentCategory::Contract` **o** nuevo case `deposit_refund` si el label del panel lo requiere con poco costo |
| `tags` | `['deposit_refund', 'generated']` |
| `meta.kind` | `deposit_refund_receipt` |
| `meta.folio` | folio DEV |
| `meta.settlement_batch_id` | batch |
| `meta.refund_expense_id` | id del expense |
| path | `documents/contract/{orgId}/deposit-refund-{contractId}-{timestamp}.pdf` |

Label en UI Documentos: “Recibo devolución depósito” (+ folio si cabe), vía `meta.kind` / categoría.

### Settlement wizard UI

Para contrato ended con `refundedDeposit > 0` y `refund_receipt_document_id` presente:

- Link **Ver recibo de devolución** → file-viewer / `documents.download` del Document  
- Mantener link a Gastos (`contractFilter`)

Sin document id (p.ej. ended antiguos): no mostrar el link de recibo (sí puede quedar Gastos / sobrante).

### Visibility of Finiquito panel on ended

Ya corregido: `canSettleContracts` no exige `isOperable()`; solo `!cancelled` + permiso `contracts.settle`. Este feature depende de eso.

## Architecture

```
ProcessContractSettlementAction
  └─ (si depositRefund > 0)
       GenerateDepositRefundReceiptFolioAction
       GenerateDepositRefundReceiptPdfAction
         └─ Pdf::loadView('pdf.deposit-refund-receipt')
         └─ Document::storeNew(Contract)
       meta.settlements[batch].refund_receipt_*
SettlementWizard (ended)
  └─ link → document download/viewer
Documents\Panel
  └─ label for kind=deposit_refund_receipt
```

### Alternativas descartadas

| Enfoque | Motivo |
|---------|--------|
| Solo guardar PDF de finiquito | No es recibo de devolución |
| Document en Expense | Menos visible en Documentos del contrato |
| Backfill ahora | Usuario eligió solo forward |

## Acceptance Criteria

1. Finiquito con refund > 0 crea exactamente un Document en el contrato con folio `DEV-` y tags `deposit_refund`.
2. `meta.settlements.{batch}` incluye `refund_receipt_folio` y `refund_receipt_document_id`.
3. Finiquito sin refund no crea Document de devolución.
4. Wizard ended con document id muestra link de recibo; sin id no lo muestra.
5. El PDF aparece en el panel Documentos del contrato.
6. Sail tests + pint verdes; sin migración de schema salvo que se añada case al enum (no requiere DB).

## Test Plan

- Unit folio: dos devoluciones mismo año → secuencia +1  
- Unit/Feature settlement: refund crea Document + meta; zero refund no crea  
- Feature SettlementWizard ended: assertSee link recibo cuando hay document id  
- Documents panel (opcional): assertSee label de recibo en contrato con Document  

## Open Decisions (resolved)

- B + generate-on-settle + forward-only  
- Folio prefix fixed `DEV-` (no settings org v1)
