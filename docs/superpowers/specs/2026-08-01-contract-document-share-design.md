# Reenviar documento de contrato (correo / WhatsApp / link) — Design Spec

**Date:** 2026-08-01  
**Status:** Approved  
**Related:** `Documents\Panel`, `ContractAgreementMail`, `ContractAgreementShareUrl`, `RenewWizard`, `PaymentReceiptShareUrl`, `receipts.send`

## Goal

En la lista de documentos del contrato, permitir **reenviar el documento de categoría Contrato** por correo, WhatsApp o link compartible. El contenido compartido es siempre el **archivo guardado** en Documentos (generado o subido a mano), no un PDF regenerado.

## Decisions (brainstorming)

| Tema | Decisión |
|------|----------|
| Qué documentos | Categoría `contract` (generado o upload manual) |
| Qué se comparte | Archivo almacenado del `Document` |
| UX | Botón «Compartir» → modal con correo / WhatsApp / copiar link |
| Correo | Envío inmediato al email del inquilino (plantilla de contrato + PDF adjunto) |
| Enfoque | Extender `Documents\Panel` (sin componente Livewire aparte) |
| Link viejo `contracts.agreement.share` | Se mantiene para renovación (PDF regenerado); este flujo usa link nuevo al archivo |

## Out of Scope (v1)

- Compartir otras categorías (INE, aval, comprobantes, etc.)
- Editar destinatario de correo antes de enviar
- Regenerar PDF desde datos del contrato en este flujo
- Cambiar el done-step de `RenewWizard` para usar el archivo guardado
- Nuevo permiso RBAC (se reutiliza `documents.view` + `receipts.send`)

## UX

En `Documents\Panel` con `variant=contract`:

1. Filas con categoría **Contrato** muestran botón **Compartir** junto a Ver / Eliminar.
2. Clic abre modal con:
   - **Enviar correo** — visible/habilitado solo si el inquilino tiene email y el usuario tiene `receipts.send`. Envía al instante. Feedback éxito/error en el modal.
   - **WhatsApp** — abre `wa.me` con mensaje renderizado desde `contract_whatsapp_template` e incluye el link firmado.
   - **Copiar link** — copia el mismo link firmado al portapapeles.
3. Otras categorías no muestran Compartir.
4. Sin teléfono del inquilino → WhatsApp usa `wa.me/?text=...` (mismo patrón que pagos).
5. Sin email → no se ofrece envío de correo (mensaje claro).

## Architecture

```text
Documents\Panel (categoría contract)
        │
        ├── DocumentShareUrl::make(documentId)  → signed /documents/{id}/shared
        ├── sendContractDocumentEmail()        → ContractDocumentMail (PDF desde Storage)
        └── WhatsApp URL                       → contract_whatsapp_template + share URL
                │
                ▼
DocumentSharedDownloadController (signed:relative)
        └── stream stored file (inline PDF)
```

### `DocumentShareUrl` (nuevo, `app/Support`)

- `temporarySignedRoute` con `absolute: false`, absoluto vía `URL::to`.
- Expiración: 7 días (igual que recibos / agreement share).
- Parámetro: `documentId`.

### Ruta pública firmada

```text
GET /documents/{documentId}/shared  → documents.shared  (middleware signed:relative)
```

`DocumentSharedDownloadController` (nuevo; separado del download autenticado):

1. Cargar `Document` con `withoutOrganizationScope()`.
2. Autorización por firma (guest OK; si hay user de otra org, igual se permite como en recibos compartidos).
3. Validar: `documentable_type` = `Contract`, `category` = `contract`.
4. Servir archivo desde `meta.disk` + `path` con `Content-Disposition: inline` y mime del registro.
5. Archivo ausente → 404. Categoría/tipo inválido → 404.

### Correo: `ContractDocumentMail` (nuevo)

- Entrada: `Document` (categoría contract ligado a Contract).
- Subject / body: plantillas de contrato existentes (`contract_email_template`).
- Placeholders: `tenant_name`, `unit_name`, `shared_contract_url`, `rent_amount`, `starts_at`, `ends_at`.
- `shared_contract_url` = `DocumentShareUrl` del documento.
- Adjunto: contenido leído de Storage del `Document` (no DomPDF / `GenerateLeaseAgreementPdfAction`).
- From: `OrganizationMailSender` como el resto de mails de org.

`ContractAgreementMail` (renovación, PDF regenerado) **no** se modifica en v1.

### Panel Livewire

Estado sugerido:

- `showShareModal`, `sharingDocumentId`
- `shareUrl`, `whatsAppUrl`
- `shareEmailStatus` / mensaje flash local

Métodos:

- `openShareModal(int $documentId)` — valida categoría + contract documentable; arma URLs.
- `closeShareModal()`
- `sendContractDocumentEmail()` — gate `receipts.send` + email inquilino; envía `ContractDocumentMail`.

Datos del inquilino vía `documentable` Contract → `tenant`.

## Permissions

| Acción | Permiso |
|--------|---------|
| Ver botón / abrir modal | `documents.view` (ya requerido al montar el panel) |
| Enviar correo | `receipts.send` |
| WhatsApp / copiar link | Sin permiso extra; el signed URL autoriza la descarga |

## Error handling

| Caso | Comportamiento |
|------|----------------|
| Sin email inquilino | Ocultar/deshabilitar envío de correo con mensaje |
| Sin teléfono | WhatsApp genérico con texto |
| Archivo faltante en disco | Share link → 404; mail → error en modal, no envía |
| Documento no-contract / otra categoría | Acciones Livewire abort 403/404; share route 404 |
| Sin `receipts.send` | No mostrar/ejecutar envío de correo |

## Testing

1. **Share URL:** guest con firma válida → 200 + PDF; sin firma → 403; documento de otra categoría → 404.
2. **Panel:** botón Compartir solo en categoría Contrato.
3. **Mail:** con `receipts.send` + email → `Mail::assertSent(ContractDocumentMail)`; adjunto = bytes del archivo almacenado; sin permiso → no envía.
4. **WhatsApp URL:** contiene host `wa.me` y el path del share firmado.

## File touch list (expected)

| Archivo | Cambio |
|---------|--------|
| `app/Livewire/Documents/Panel.php` | Modal share + send email |
| `resources/views/livewire/documents/panel.blade.php` | Botón + modal |
| `app/Support/DocumentShareUrl.php` | Nuevo |
| `app/Http/Controllers/Documents/...` | Stream signed shared file |
| `routes/web.php` | Ruta `documents.shared` |
| `app/Mail/ContractDocumentMail.php` | Nuevo |
| `resources/views/emails/...` | Vista mail (reutilizar patrón contract-agreement) |
| `lang/es|en/...` | Strings UI |
| `tests/Feature/...` | Share URL + panel send |
