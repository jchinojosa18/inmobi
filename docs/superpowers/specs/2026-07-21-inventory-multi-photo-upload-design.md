# Inventario maestro — carga múltiple de fotos — Design Spec

**Date:** 2026-07-21  
**Status:** Approved  
**Related:** `docs/superpowers/specs/2026-07-16-unit-inventory-design.md`

## Goal

Permitir subir **varias fotos a la vez** por ítem del inventario maestro. Hoy el input acepta un solo archivo.

## Out of Scope

- Subir el tope de 5 fotos por ítem
- Auto-upload al seleccionar archivos
- Action/`Job` nuevos para la subida
- Cambios en snapshots de inventario por contrato
- UI de documentos genéricos fuera de `Units\InventoryPanel`

## Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Enfoque | Multi-file en el mismo Livewire | Diff mínimo; reutiliza permisos, MIME, storage y tope |
| Flujo UX | Elegir archivos → clic “Subir” | Igual que hoy; sin sorpresas |
| Lote vs tope | Rechazo total si no caben todas | Evita subidas parciales confusas |
| Validación inválida | Rechazo total del lote | Consistente con rechazo por tope |
| Auditoría | Un evento por foto creada | No cambia semántica de `inventory.photo_uploaded` |

## Behavior

1. El `<input type="file">` tiene `multiple`.
2. El usuario elige N imágenes (JPG/PNG, ≤ 5 MB c/u) y confirma con el botón de subir.
3. Antes de persistir:
   - `fotos_actuales + N > 5` → error de tope; **ninguna** foto nueva.
   - Cualquier archivo falla `image|mimes:jpg,jpeg,png|max:5120` → error; **ninguna** foto nueva.
4. Si todo es válido: crear N `Document` (morph `UnitInventoryItem`, `UNIT_INVENTORY_PHOTO`) en una transacción DB.
5. Tras éxito: limpiar `photoUploads.{itemId}`, incrementar `photoUploadInputKeys`, dispatch `inventory-photo-uploaded`.
6. Un solo archivo sigue funcionando (lote de tamaño 1).

Aplica en cualquier pantalla que use `Units\InventoryPanel` (deptos y casas/locales).

## UI

Archivo: `resources/views/livewire/units/inventory-panel.blade.php`

- Atributo `multiple` en el input del gallery modal.
- Preview: mostrar conteo o lista de nombres seleccionados (Alpine).
- Copy plural en i18n (`choose_photo`, `upload_photo`, `uploading_photo`, mensaje de éxito).
- Loading/`wire:target` apuntan al binding del ítem.

## Implementation

Archivo: `app/Livewire/Units/InventoryPanel.php`

- `photoUploads[$itemId]` pasa a ser **array** de `TemporaryUploadedFile`.
- Validación en `uploadPhoto`:
  - `photoUploads.{id}` → `required|array|min:1`
  - `photoUploads.{id}.*` → `image|mimes:jpg,jpeg,png|max:5120`
  - Check explícito de cupo: `documents()->count() + count(lote) <= MAX_PHOTOS_PER_ITEM` (5)
- Loop de store + `Document::create` + `AuditLogger` por foto, envuelto en `DB::transaction`.
- Mensajes de validación reutilizan / extienden claves en `lang/{es,en}/inventory.php`.

Sin cambios de modelo, migraciones ni permisos.

## Testing

Extender `tests/Feature/Units/UnitInventoryPanelTest.php`:

| Caso | Expectativa |
|------|-------------|
| Lote de 2+ fotos válidas | N documentos; sin errores; event dispatched |
| 3 existentes + lote de 3 | Error de tope; 0 documentos nuevos |
| Lote con un archivo inválido | Error; 0 documentos nuevos |
| Una sola foto (regresión) | Sigue creando 1 documento |

```bash
./vendor/bin/sail test --filter=UnitInventoryPanel
./vendor/bin/sail pint --dirty
```

## Success Criteria

- Se pueden seleccionar y subir varias fotos en un solo envío
- El tope de 5 por ítem se respeta con rechazo total del lote
- Copy ES/EN en plural
- Tests del panel pasan; Pint limpio
