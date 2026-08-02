# Generar PDF al crear/editar contrato — Design Spec

**Date:** 2026-08-01  
**Status:** Approved  
**Related:** `CreateModal`, `GenerateLeaseAgreementPdfAction`, `RenewContractAction`, `RenewWizard`, `ContractAgreementMail`, `ContractAgreementShareUrl`, `Documents\Panel`

## Goal

Al **crear** un contrato nuevo, generar y guardar el PDF de arrendamiento (Document categoría Contrato), ofrecer checkbox de envío por email en el formulario, y un paso final con ver PDF / WhatsApp / copiar link (como renovación). Al **editar**, regenerar y reemplazar ese Document.

## Decisions (brainstorming)

| Tema | Decisión |
|------|----------|
| Alcance create | PDF + checkbox email + paso done (correo / WA / link) |
| `ends_at` | Obligatorio en alta; en edición también obligatorio para poder regenerar |
| Edit | Regenerar y reemplazar Document categoría `contract` (sin done-step ni checkbox) |
| Enfoque | Extender `CreateModal` (sin Action nueva v1) |
| Share en done | `ContractAgreementShareUrl` (PDF regenerado), igual que renovación |
| Mail | `ContractAgreementMail` (igual que renovación) |

## Out of Scope (v1)

- Nuevo `CreateContractAction` / refactor grande del modal
- Usar `DocumentShareUrl` / `ContractDocumentMail` en el done de create (queda el patrón de renovación)
- Regenerar PDF desde observers del modelo
- Botón suelto «Regenerar PDF» fuera de create/edit
- Cambiar el flujo de renovación

## UX

### Create

1. Formulario actual + `ends_at` required.
2. Checkbox «Enviar contrato por email» si el inquilino tiene email y el usuario tiene `receipts.send` (default on cuando ambos).
3. Guardar → genera PDF Document → opcionalmente envía mail → `step = done`.
4. Done: ver PDF (file viewer), copiar link firmado, WhatsApp (`contract_whatsapp_template`), ir a ficha del contrato.
5. No redirect automático a show (el done lo sustituye).

### Edit

1. Sin checkbox ni paso done.
2. Guardar → update → reemplazar Document categoría Contrato.
3. Flash éxito + cerrar modal (como hoy).
4. Sin `ends_at` o sin `landlord_name` → error de validación; no completar el guardado sin PDF regenerable.

## Architecture

```text
CreateModal::save()
        │
        ├── validate (ends_at, landlord_name, …)
        ├── TX: create | update Contract
        │
        ├── GenerateLeaseAgreementPdfAction::execute
        │     └── Document category=contract (edit: delete previous first)
        │
        ├── create + send_email? → ContractAgreementMail
        │
        └── create → pdfUrl / shareUrl / whatsAppUrl → step=done
            edit  → flash + close modal
```

### Create path

1. Assert `landlord_name` configured (same helper pattern as `RenewContractAction`).
2. Validate including `ends_at` required.
3. DB transaction: create contract (existing fields/side effects: rent charges hook, audit).
4. After TX: `GenerateLeaseAgreementPdfAction::execute($contract, userId)`.
5. If `send_email` and tenant email and `receipts.send`: `Mail::to(...)->send(new ContractAgreementMail($contract))`.
6. Build `pdfUrl` (file viewer / agreement.pdf route as RenewWizard), `shareUrl` via `ContractAgreementShareUrl::make`, `whatsAppUrl` via contract WhatsApp template → `step = 'done'`.

### Edit path

1. Assert `landlord_name`; require `ends_at`.
2. TX: update contract.
3. After TX: find existing Document for this contract with category `contract`; delete file + soft-delete (mirror `Documents\Panel::deleteDocument` semantics); then `GenerateLeaseAgreementPdfAction::execute`.
4. Flash + close (no done step).

### Reuse

- `GenerateLeaseAgreementPdfAction`
- `ContractAgreementMail`
- `ContractAgreementShareUrl`
- Org settings: `landlord_name`, `contract_email_template`, `contract_whatsapp_template`
- Done-step UI patterns from `RenewWizard`

### Unchanged

- `documents.shared` / panel share of stored Document
- On-demand `contracts.agreement.pdf` / `contracts.agreement.share`
- Renewal flow

## Permissions

| Acción | Permiso |
|--------|---------|
| Create/edit + PDF generate | `contracts.manage` |
| Checkbox + send email | `receipts.send` + tenant email |
| WhatsApp / copy link on done | No extra permission |

## Error handling

| Caso | Comportamiento |
|------|----------------|
| Missing `landlord_name` | Validation error; do not save |
| Create/edit missing `ends_at` | Validation error |
| PDF generation fails after create TX | Surface error; contract may already exist (same trade-off as renewal) |
| Mail fails | Do not fail create; PDF + done still available |
| Edit with no prior contract Document | Just create the new one |

## Testing

1. Create with `ends_at` → Document category `contract` exists.
2. Create without `ends_at` → validation error.
3. Create without `landlord_name` → validation error; no contract.
4. Create + `send_email` + `receipts.send` + tenant email → `ContractAgreementMail` sent.
5. Create done step populates `shareUrl` / `whatsAppUrl`.
6. Edit replaces category-`contract` Document (only one remains).
7. Edit without `ends_at` → validation error.

## File touch list (expected)

| Archivo | Cambio |
|---------|--------|
| `app/Livewire/Contracts/CreateModal.php` | PDF after save, send_email, done step, ends_at rules |
| `resources/views/livewire/contracts/create-modal.blade.php` | Checkbox + done UI |
| `lang/es|en/contracts.php` (or related) | Strings |
| `tests/Feature/Contracts/ContractCreateModalTest.php` (extend) | Create/edit PDF + email + validation |
