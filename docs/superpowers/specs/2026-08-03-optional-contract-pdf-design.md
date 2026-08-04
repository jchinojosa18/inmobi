# Generar PDF de contrato opcional (crear / editar / renovar) — Design Spec

**Date:** 2026-08-03  
**Status:** Approved  
**Related:** `CreateModal`, `RenewWizard`, `RenewContractAction`, `GenerateLeaseAgreementPdfAction`, `ContractAgreementMail`

## Goal

Al crear, editar o renovar un contrato, la generación del PDF de contrato (y su guardado en Documentos) debe ser **opcional** mediante un checkbox en el formulario. Por defecto sigue generando PDF (comportamiento actual).

## Decisions

| Tema | Decisión |
|------|----------|
| Default del checkbox | Marcado (`generate_pdf = true`) |
| Alcance | Crear, editar y renovar |
| Correo al inquilino | Solo si `generate_pdf` está marcado; si se desmarca PDF, ocultar/deshabilitar envío y forzar `send_email = false` |
| Done-step sin PDF | Solo éxito + «Ver detalles»; sin Ver PDF / Compartir link / WhatsApp |
| Documento existente al editar sin PDF | No regenerar ni borrar el documento de contrato ya guardado |
| Enfoque | Flag en Livewire + gate en Action de renovación (mismo patrón que `send_email`) |

## Out of Scope

- Preferencia por organización en Settings
- Regenerar PDF on-demand al compartir sin documento guardado
- Cambiar el flujo de reenvío desde `Documents\Panel` (archivo almacenado)
- Cambios de permisos RBAC o migraciones

## UX

### Formularios (`CreateModal`, `RenewWizard`)

1. Checkbox **«Generar PDF del contrato»** (marcado por defecto), visible en crear, editar y renovar.
2. Checkbox de enviar correo (ya existente) solo visible/habilitado cuando:
   - `generate_pdf` está marcado, **y**
   - el usuario tiene `receipts.send`, **y**
   - el inquilino tiene email.
3. Al desmarcar `generate_pdf` (vía `updatedGeneratePdf` o equivalente): `send_email = false`.

### Paso «listo» (crear / renovar)

| `generate_pdf` | Acciones mostradas |
|----------------|--------------------|
| On | Ver PDF, Compartir link, WhatsApp, Ver detalles (como hoy) |
| Off | Solo Ver detalles (mensaje de éxito sin acciones de share) |

Editar: sin paso «listo»; al guardar sin PDF, flash de éxito y cierre del modal (como hoy).

## Architecture

```text
CreateModal / RenewWizard
        │
        ├── generate_pdf (bool, default true)
        │
        ├── si generate_pdf:
        │       GenerateLeaseAgreementPdfAction
        │       (+ ContractAgreementMail si send_email)
        │       armar pdfUrl / shareUrl / whatsAppUrl
        │
        └── si !generate_pdf:
                no PDF, no mail, URLs null → done-step solo «Ver detalles»
```

### `CreateModal`

- Propiedad pública `bool $generate_pdf = true`.
- **Crear:** llamar a `GenerateLeaseAgreementPdfAction` solo si `generate_pdf`; mail y URLs de share solo en ese caso.
- **Editar:** llamar a `replaceContractAgreementDocument` solo si `generate_pdf`; si false, omitir regeneración (dejar documentos existentes intactos).
- Validación de `landlord_name` para PDF: solo cuando se va a generar PDF.

### `RenewWizard` + `RenewContractAction`

- Propiedad `bool $generate_pdf = true` en el wizard.
- Pasar `generate_pdf` en el `input` del action.
- En `RenewContractAction::execute`: si `generate_pdf` es false, no ejecutar PDF ni mail; `RenewContractResult::$document` puede ser `null`.
- Done-step: armar `pdfUrl` / `shareUrl` / `whatsAppUrl` solo si se generó PDF.

### Blades

- Checkbox junto al de correo (crear/editar/renovar).
- Condicionar visibilidad del correo a `generate_pdf`.
- Done-step: las acciones de PDF/share ya están detrás de `@if ($pdfUrl)` / `@if ($shareUrl)` / `@if ($whatsAppUrl)`; dejar esas URLs en `null` cuando no hay PDF.

## Permissions

Sin cambios. Se reutilizan `contracts.manage` y `receipts.send` (correo).

## Error handling

| Caso | Comportamiento |
|------|----------------|
| `generate_pdf` off | Contrato se guarda/renueva sin documento PDF; sin error de landlord |
| `generate_pdf` on + landlord vacío | Error de validación existente (no se “salta” el guard) |
| `generate_pdf` off + `send_email` true en request | Ignorar mail (forzar false en servidor) |
| Editar con documento manual y `generate_pdf` on | Misma regla actual de bloqueo por documento manual |
| Editar con `generate_pdf` off | No aplica lógica de regeneración / bloqueo manual |

## Testing

1. **Crear con PDF off:** contrato creado; no hay `Document` generado de categoría contrato; done-step sin PDF/link/WhatsApp.
2. **Crear con PDF on:** comportamiento actual (documento + URLs).
3. **Editar con PDF off:** datos actualizados; documento existente intacto.
4. **Editar con PDF on:** regenera como hoy (respetando bloqueo de documento manual).
5. **Renovar con PDF off:** nuevo contrato; sin documento; sin mail; done-step solo Ver detalles.
6. **Renovar con PDF on + send_email:** documento + mail como hoy.
7. **PDF off fuerza no-mail:** aunque `send_email` esté true en estado, no se envía correo.

## File touch list (expected)

| Archivo | Cambio |
|---------|--------|
| `app/Livewire/Contracts/CreateModal.php` | `generate_pdf` + gates |
| `resources/views/livewire/contracts/create-modal.blade.php` | Checkbox + correo condicional |
| `app/Livewire/Contracts/RenewWizard.php` | `generate_pdf` + input/action + URLs |
| `resources/views/livewire/contracts/renew-wizard.blade.php` | Checkbox + correo condicional |
| `app/Actions/Contracts/RenewContractAction.php` | Gate PDF/mail por `generate_pdf` |
| `app/Actions/Contracts/RenewContractResult.php` | `document` nullable si aplica |
| `lang/es|en/...` | String del checkbox |
| `tests/Feature/Contracts/...` | Casos create/edit/renew con/sin PDF |
