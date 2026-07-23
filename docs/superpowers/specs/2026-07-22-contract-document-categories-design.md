# Documentos de contrato con categoría — Design Spec

**Date:** 2026-07-22  
**Status:** Approved (design); pending spec review  
**Related:** `docs/DOCUMENT_STORAGE.md`, `app/Livewire/Documents/Panel.php`, `contracts/show`

## Goal

Permitir subir documentos PDF a un contrato con **nombre/categoría predefinida** (Contrato, Aval, Identificación oficial, etc.), mostrarlos con etiqueta legible en la UI, y garantizar **un solo archivo por categoría** por contrato. El usuario debe poder eliminar un documento (mismo permiso que subir) antes de subir otro del mismo tipo.

## Out of Scope

- Cambiar reglas de documentos en pagos, gastos, unidades o cargos (siguen JPG/PNG/PDF sin categoría).
- Categorías configurables por organización en `/settings`.
- Permiso nuevo o cambios en roles/seeders.
- Renombrar categoría de un documento ya subido (eliminar y volver a subir).
- Versionado histórico de documentos reemplazados.

## User Stories

1. Como usuario con `documents.view`, quiero ver los documentos del contrato agrupados por categoría con nombre legible.
2. Como usuario con `documents.upload`, quiero elegir el tipo de documento y subir solo PDF.
3. Como usuario con `documents.upload`, no debo poder subir dos archivos de la misma categoría en el mismo contrato.
4. Como usuario con `documents.upload`, quiero eliminar un documento para poder subir otro de esa categoría.
5. Como usuario en otros módulos (pagos, inventario, etc.), el panel de documentos genérico no debe cambiar de comportamiento.

## Architecture

### Enfoque elegido

Extender `App\Livewire\Documents\Panel` con prop `variant` (`default` | `contract`). Cuando `variant=contract`:

- Select de categoría obligatorio.
- Validación solo PDF.
- Unicidad por categoría (BD + validación Livewire).
- Acción `delete` con confirmación en UI.

Nueva columna `category` en `documents` + índice único compuesto.

### Alternativas descartadas

| Enfoque | Motivo de descarte |
|---------|-------------------|
| Componente `ContractDocumentsPanel` separado | Duplica subida, descarga, org scope y MonthCloseGuard |
| Categoría solo en `meta` JSON | Sin constraint en BD; condiciones de carrera en unicidad |
| Reutilizar columna `type` para categoría | `type` ya identifica el origen (`CONTRACT_DOCUMENT`); mezclar semánticas confunde otros flujos |

## Data Model

### Migración: columna `category` en `documents`

| Columna | Tipo | Notas |
|---------|------|-------|
| `category` | `string(50)` nullable | Slug de categoría; `null` en documentos no-contract |

**Índice único:** `(organization_id, documentable_type, documentable_id, category)` — en MySQL el índice único permite múltiples filas con `category IS NULL` (comportamiento estándar).

### Enum `App\Support\ContractDocumentCategory`

Backed enum (`string`) con valores y método `label()` vía `__('contracts.document_categories.{value}')`:

| Valor | Etiqueta (es) |
|-------|---------------|
| `contract` | Contrato |
| `guarantor` | Aval |
| `id` | Identificación oficial |
| `address_proof` | Comprobante de domicilio |
| `payslip` | Recibo de nómina |
| `bank_statements` | Estados de cuenta |
| `commercial_references` | Referencias comerciales |

Métodos estáticos útiles: `values()`, `options()` (value => label para select).

### Modelo `Document`

- Añadir `category` a `$fillable` y `auditableAttributes`.
- Cast opcional a `ContractDocumentCategory::class` cuando no es null.

## UI

### Activación

En `resources/views/livewire/contracts/show.blade.php`, el panel existente pasa:

```blade
<livewire:documents.panel
    :documentable-type="\App\Models\Contract::class"
    :documentable-id="$contract->id"
    :title="__('contracts.contract_documents')"
    variant="contract"
    :key="'contract-documents-'.$contract->id"
/>
```

Otros usos del panel **no** pasan `variant` (default).

### Formulario de subida (variant contract)

- `<select>` con categorías disponibles (excluir las ya usadas en el contrato).
- `<input type="file" accept=".pdf">`.
- Texto de ayuda: solo PDF, máximo 5 MB.
- Botón subir (sin cambios de permiso: `documents.upload`).

### Tabla de documentos (variant contract)

| Columna | Contenido |
|---------|-----------|
| Nombre | Etiqueta de `category` |
| Archivo | Enlace de descarga (nombre de archivo original o basename del path) |
| Tamaño | Formato legible |
| Fecha | `created_at` |
| Acciones | Eliminar (si `documents.upload`) |

### Panel default (sin cambios funcionales)

- Sin select de categoría.
- JPG, PNG, PDF.
- Tabla sin columna Nombre ni acción Eliminar (comportamiento actual).

## Business Rules

### Subida (contract variant)

1. `category` requerido; debe ser valor válido del enum.
2. Archivo requerido; `mimes:pdf`; máximo 5 MB (5120 KB).
3. Antes de crear: comprobar que no exista otro `Document` activo (no soft-deleted) con la misma `(organization_id, documentable_type, documentable_id, category)`.
4. Persistir `category`, `type` = `CONTRACT_DOCUMENT` (como hoy), path/mime/size/meta igual que flujo actual.
5. `MonthCloseGuard` en `creating` (ya existe en modelo).

### Eliminación (contract variant)

1. Requiere `documents.upload` (no `documents.delete`).
2. Soft delete del registro + eliminar archivo del disk configurado (`meta.disk` o `config('filesystems.documents_disk')`).
3. `MonthCloseGuard` en `deleting` (ya existe).
4. Audit log: acción `document.deleted` con categoría en meta.

### Descarga

Sin cambios: `Documents\DownloadController` + permiso `documents.view`.

## Translations

Añadir en `lang/es/contracts.php` y `lang/en/contracts.php`:

- `document_categories.{slug}` — etiquetas del enum.
- `document_category` — label del select.
- `document_category_required` — validación.
- `document_category_taken` — categoría ya usada.
- `document_pdf_only` — validación mimes.
- `document_deleted_success` — flash tras eliminar.
- `delete_document` — botón/confirmación.

Actualizar `lang/*/documents.php`:

- `allowed_types_contract` — "Permitidos: PDF. Máximo 5 MB." (variant contract).

## Testing

Nuevo archivo `tests/Feature/Contracts/ContractDocumentsPanelTest.php`:

| Test | Comportamiento |
|------|----------------|
| `test_uploads_pdf_with_category` | Subida exitosa; tabla muestra etiqueta |
| `test_rejects_non_pdf` | JPG/PNG rechazados en variant contract |
| `test_rejects_duplicate_category` | Segunda subida misma categoría → error validación |
| `test_delete_frees_category` | Eliminar permite subir de nuevo |
| `test_delete_requires_upload_permission` | Usuario solo `documents.view` → 403 |
| `test_used_categories_hidden_from_select` | Categoría ocupada no aparece en opciones |
| `test_generic_panel_unchanged` | Panel en `Unit` sigue aceptando JPG |

Ejecutar también `DocumentSecurityTest` para regresiones.

## Files to Touch (implementation reference)

| Archivo | Cambio |
|---------|--------|
| `database/migrations/..._add_category_to_documents_table.php` | Nueva columna + índice único |
| `app/Support/ContractDocumentCategory.php` | Enum + helpers |
| `app/Models/Document.php` | fillable, cast, auditable |
| `app/Livewire/Documents/Panel.php` | variant, category, delete, reglas condicionales |
| `resources/views/livewire/documents/panel.blade.php` | UI condicional |
| `resources/views/livewire/contracts/show.blade.php` | `variant="contract"` |
| `lang/es/contracts.php`, `lang/en/contracts.php` | Traducciones |
| `lang/es/documents.php`, `lang/en/documents.php` | Hint PDF-only |
| `database/factories/DocumentFactory.php` | `category` opcional |
| `tests/Feature/Contracts/ContractDocumentsPanelTest.php` | Tests nuevos |

## Risks & Mitigations

| Riesgo | Mitigación |
|--------|------------|
| Documentos de contrato existentes sin `category` | Quedan visibles en tabla genérica (sin nombre); no bloquean índice único (`category` null). Opcional: migración de datos no requerida en v1. |
| Condición de carrera en unicidad | Índice único en BD + manejo de `QueryException` con mensaje amigable |
| Eliminar archivo falla tras soft delete | Transacción: soft delete solo si `Storage::delete` ok, o registrar en meta si falla (seguir patrón de `InventoryPanel`) |
