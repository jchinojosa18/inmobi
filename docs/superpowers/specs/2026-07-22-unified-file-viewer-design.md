# Visor unificado de archivos — Design Spec

**Date:** 2026-07-22  
**Status:** Approved (design); pending spec review  
**Related:** `docs/DOCUMENT_STORAGE.md`, `inventory-panel` photo viewer, `Documents\DownloadController`

## Goal

Establecer un **estándar de sistema**: toda imagen o archivo en la app se abre **en la misma página** (overlay modal), con opción de **descargar** mientras se visualiza. Sin eliminar ni editar desde el visor. Comportamiento similar al visor de fotos del inventario, pero genérico y reutilizable.

## Decisions (brainstorming)

| Tema | Decisión |
|------|----------|
| Alcance | **C)** Documentos subidos + PDFs generados + cualquier enlace a archivo en la app autenticada |
| Navegación | **C)** Galería anterior/siguiente si el contexto provee varios ítems; un solo archivo si no |
| Presentación | **A)** Overlay modal global (recomendado y aprobado) |
| Acciones en visor | Cerrar, Descargar, navegación (si galería). **Sin eliminar** |

## Out of Scope (v1)

- Visor en rutas públicas sin layout (`payments.receipt.share` firmada) — puede seguir abriendo PDF en pestaña o inline según navegador; v2 unificar si hace falta
- Edición de archivos
- Eliminar desde el visor (la eliminación sigue en listas/formularios con `confirm-modal`)
- Office/docx preview (solo imagen, PDF inline, fallback descarga)
- Compartir enlace directo al visor (sin URL dedicada)

## User Stories

1. Como usuario, al hacer clic en un documento de contrato quiero verlo en pantalla sin salir de la página.
2. Como usuario, quiero descargar el archivo que estoy viendo con un botón claro.
3. Como usuario, si hay varios documentos en la misma sección, quiero ir al anterior/siguiente sin cerrar el visor.
4. Como usuario, al abrir un recibo PDF de pago o depósito quiero el mismo visor, no una pestaña nueva.
5. Como desarrollador, quiero un solo componente y una regla en Cursor para no usar `target="_blank"` en archivos internos.

## Architecture

### Enfoque elegido

**Componente Blade global + Alpine** montado en `layouts/app.blade.php`, activado por evento de ventana `open-file-viewer`. Enlaces estándar reemplazados por `<x-ui.file-viewer-trigger>` (o helper PHP) que previene navegación y despacha el evento.

### Alternativas descartadas

| Enfoque | Motivo |
|---------|--------|
| Ruta `/files/view` dedicada | Rompe flujo Livewire; más estado en URL; innecesario para uso interno |
| iframe por cada pantalla | Duplicación; inconsistente con inventario |
| Solo `target="_blank"` con `inline` | No cumple “misma página”; experiencia fragmentada |

## Data Contract (evento Alpine)

```js
$dispatch('open-file-viewer', {
  index: 0,
  title: 'Documentos del contrato', // opcional
  items: [
    {
      label: 'Contrato',
      viewUrl: 'https://.../documents/12/download?inline=1',
      downloadUrl: 'https://.../documents/12/download',
      mime: 'application/pdf', // o type: 'image' | 'pdf' | 'download'
    },
  ],
})
```

- `viewUrl`: stream con `Content-Disposition: inline` cuando el navegador lo permita
- `downloadUrl`: attachment / descarga forzada
- `mime` o `type` determina render: `<img>` vs `<iframe>` vs panel “vista no disponible + descargar”

## UI Component: `x-ui.file-viewer`

Montado una vez en `app.blade.php` (junto a modales globales).

### Layout

- Overlay `fixed inset-0 z-[60]` fondo `bg-black/80`
- Panel central `max-w-6xl`, altura adaptable
- **Header:** título/etiqueta del ítem, contador `2/5` si galería, botón Cerrar (X)
- **Toolbar:** Descargar (enlace a `downloadUrl`), Anterior/Siguiente si `items.length > 1`
- **Body:**
  - `image/*` → `<img class="max-h-[75vh] object-contain">`
  - `application/pdf` → `<iframe class="h-[75vh] w-full rounded-lg bg-white">`
  - Otros → icono + mensaje + botón Descargar
- Teclado: `Escape` cierra; flechas ← → navegan si galería
- Touch: swipe opcional (reutilizar lógica inventario)

### Sin eliminar

No incluir botón Eliminar ni acciones destructivas. El visor del inventario actual pierde el botón rojo de eliminar; eliminar queda en galería/lista con `confirm-modal`.

## Backend: inline streaming

### Ya existe

`GET /documents/{document}/download?inline=1` → `Content-Disposition: inline` (`DownloadController`).

### Añadir en v1

Soportar `?inline=1` (o header equivalente) en:

| Ruta | Controller |
|------|------------|
| `payments.receipt.pdf` | `PaymentReceiptPdfController` |
| `deposits.receipt.pdf` | `DepositReceiptPdfController` |
| `contracts.settlements.pdf` | `ContractSettlementPdfController` |

Cuando `inline=1`: `Content-Disposition: inline` en la respuesta PDF stream. Sin `inline`: comportamiento actual (descarga).

**Seguridad:** mismos middleware/permisos que hoy; no exponer rutas nuevas sin auth.

## Frontend integration

### Componente trigger: `x-ui.file-viewer-trigger`

Props:

- `items` — array serializable (o `items-json` desde PHP)
- `index` — índice al hacer clic
- `label` — texto del enlace (default: nombre archivo)

Comportamiento: `@click.prevent` → dispatch `open-file-viewer`.

### Migración de pantallas (v1)

| Ubicación | Cambio |
|-----------|--------|
| `documents/panel` (contrato y genérico) | trigger + lista de ítems del panel |
| `units/inventory-panel` | reemplazar visor Alpine local por evento global; quitar delete del visor |
| `payments/show` | recibo + evidencias |
| `contracts/show` | recibo PDF en pagos recientes |
| `contracts/deposit-hold-form` | comprobante depósito |
| `contracts/settlement-wizard` | PDF finiquito |
| `dashboard/index` | recibo rápido |
| `payments/quick-register-modal` | enlace post-pago |

**Regla:** en vistas autenticadas, **prohibido** `target="_blank"` para archivos/PDFs internos salvo excepción documentada (p.ej. link compartible firmado externo).

### Regla Cursor (`.cursor/rules/inmo-livewire.mdc`)

```markdown
## Visualización de archivos

- **Nunca** abrir archivos internos con `target="_blank"` ni navegación directa a PDF/imagen.
- Usar `<x-ui.file-viewer-trigger>` o `$dispatch('open-file-viewer', …)`.
- El visor global solo permite **ver** y **descargar**; eliminar/editar fuera del visor.
- PDFs: `viewUrl` con `?inline=1`; `downloadUrl` sin inline.
- Galería: pasar todos los ítems del contexto (lista, galería, tabla).
```

## i18n

Nuevo archivo `lang/es/file_viewer.php` y `lang/en/file_viewer.php`:

- `title` — Visor de archivo
- `download` — Descargar
- `close` — Cerrar
- `previous` / `next`
- `unsupported_preview` — Vista previa no disponible para este tipo de archivo.
- `counter` — `:current / :total`

## Testing

| Test | Tipo |
|------|------|
| `DocumentSecurityTest` — inline sigue OK | Feature (existente) |
| PDF controllers con `?inline=1` → header inline | Feature nuevo |
| Layout incluye componente file-viewer | Feature |
| `documents/panel` — clic abre evento (Livewire::test assertDispatched browser event o assertSee trigger attrs) | Feature opcional |

Smoke manual: contrato PDF, foto inventario, recibo pago, finiquito PDF.

## Implementation phases

**Fase 1 (MVP):** componente global + inline PDF en controllers + `documents/panel` + regla Cursor  
**Fase 2:** inventario migra a visor global (elimina duplicado Alpine)  
**Fase 3:** pagos, depósitos, finiquito, dashboard, quick-register

Fases 2–3 pueden ser un solo PR si el trigger es reutilizable.

## Risks

| Riesgo | Mitigación |
|--------|------------|
| iframe PDF bloqueado por CSP | Misma origin; sin CSP restrictivo en archivos autenticados |
| PDF grande en móvil | iframe full viewport; botón descargar siempre visible |
| Links compartibles (`share_url`) | Fuera de alcance v1; documentar excepción |
| Duplicar visor inventario durante migración | Fase 2 elimina código Alpine duplicado |
