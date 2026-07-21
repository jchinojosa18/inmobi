# Inventario maestro de unidades — Design Spec

**Date:** 2026-07-16  
**Status:** Approved  
**Related:** `docs/AI_ONBOARDING.md` (dominio Units + Documents)

## Goal

Permitir gestionar el **inventario maestro** de cada departamento (unidad en edificio): ítems estructurados con nombre libre, cantidad, condición, notas opcionales y fotos de evidencia por ítem. Acceso desde una página de detalle de unidad.

## Out of Scope (v1)

- Snapshots de inventario por contrato (entrada / salida / comparación)
- Catálogo de ítems configurable por organización en `/settings`
- Inventario en casas/locales (`/houses/{property}`) — fase 2: ver `2026-07-21-standalone-house-local-inventory-design.md` (terreno sigue fuera)
- PDF en fotos de inventario (solo imágenes JPG/PNG)
- Permisos nuevos (reutilizar `units.*` y `documents.*`)

## Future (documented for alignment)

Los snapshots por contrato serán tablas separadas (`contract_inventory_snapshots` + items) que copien el estado del maestro al inicio y al fin del arrendamiento. El maestro **no** se modifica por contratos.

## User Stories

1. Como usuario con `units.view`, quiero ver el inventario maestro de un departamento para conocer qué hay en la unidad.
2. Como usuario con `units.manage`, quiero agregar, editar y eliminar ítems del inventario.
3. Como usuario con `documents.upload`, quiero subir fotos de evidencia por ítem.
4. Como usuario con `documents.view`, quiero ver y descargar las fotos de cada ítem.
5. Como usuario con `units.manage`, no debo poder eliminar una unidad que tenga ítems de inventario.

## Architecture

### Enfoque elegido

Tabla dedicada `unit_inventory_items` + fotos vía morph `Document` existente sobre `UnitInventoryItem`. Página Livewire `Units\Show` con componente embebido `Units\InventoryPanel`.

### Alternativas descartadas

| Enfoque | Motivo de descarte |
|---------|-------------------|
| JSON en `units` | No consultable; difícil adjuntar fotos por ítem; malo para snapshots |
| Sistema de versiones genérico desde v1 | Sobreingeniería; snapshots van en fase 2 |

## Data Model

### Tabla `unit_inventory_items`

| Columna | Tipo | Notas |
|---------|------|-------|
| `id` | bigint PK | |
| `organization_id` | FK `organizations` | Multi-tenant |
| `unit_id` | FK `units` | Unidad dueña |
| `name` | string(255) | Texto libre, requerido |
| `quantity` | unsigned int | Default `1`, mínimo `1` |
| `condition` | string(16) | `good`, `fair`, `poor` |
| `notes` | text nullable | Observaciones |
| `sort_order` | int | Default `0` |
| `created_at`, `updated_at` | timestamps | |
| `deleted_at` | soft delete | |

**Índices:** `(organization_id, unit_id)`, `(unit_id, sort_order)`.

### Modelo `UnitInventoryItem`

- Extiende `OrganizationScopedModel`
- Traits: `Auditable`, `HasFactory`, `SoftDeletes`
- Constantes: `CONDITION_GOOD`, `CONDITION_FAIR`, `CONDITION_POOR`
- Relaciones: `unit()`, `documents()` (morphMany `Document`)
- `auditableAttributes`: `unit_id`, `name`, `quantity`, `condition`, `notes`, `sort_order`

### Relación en `Unit`

```php
public function inventoryItems(): HasMany
{
    return $this->hasMany(UnitInventoryItem::class);
}
```

### Fotos (`Document`)

- `documentable_type` = `App\Models\UnitInventoryItem`
- Carpeta: `documents/unitinventoryitem/{organization_id}/`
- MIME permitidos: `jpg`, `jpeg`, `png`
- Tamaño máximo: 5 MB (igual que documentos actuales)
- Límite: **5 fotos por ítem** en v1
- `type` = `UNIT_INVENTORY_PHOTO`
- `tags` = `['inventory', 'photo']`
- Sin bloqueo por cierre mensual (`MonthCloseGuard` retorna `null` para documentables sin fecha financiera)

### Reglas de eliminación de unidad

Actualizar `unitHasOperationalHistory()` en `Units\Index` para incluir:

```php
|| $unit->inventoryItems()->exists()
```

También actualizar `deletableUnitsQuery()` con `whereDoesntHave('inventoryItems')` y `withCount('inventoryItems')` en el paginador.

## Routes & Permissions

| Ruta | Componente | Middleware |
|------|------------|------------|
| `GET /properties/{property}/units/{unit}` | `Units\Show` | `auth`, `verified`, `permission:units.view` |

Nombre de ruta: `properties.units.show`.

**Validaciones en `mount`:**
- Propiedad no standalone (edificio); si es standalone → redirect a `houses.show`
- `unit.property_id === property.id`
- `unit.organization_id === auth user organization_id`

| Acción | Permiso |
|--------|---------|
| Ver página e inventario | `units.view` |
| CRUD ítems | `units.manage` |
| Ver fotos | `documents.view` |
| Subir fotos | `documents.upload` |
| Eliminar fotos | `documents.delete` (si se expone en UI; opcional v1 — solo upload/view) |

## UI

### Página `Units\Show`

```
┌──────────────────────────────────────────────────────────┐
│ ← Volver    Depto 101 · EDIFICIO CENTRO                 │
├──────────────────────────────────────────────────────────┤
│ [Código, piso, estado, notas de la unidad]              │
├──────────────────────────────────────────────────────────┤
│ INVENTARIO MAESTRO                         [+ Agregar]   │
│ ┌────────────────────────────────────────────────────┐  │
│ │ Nombre │ Cant. │ Condición │ Fotos │ Acciones      │  │
│ │ Refri. │   1   │ Bueno     │ 🖼🖼  │ Editar Elim.  │  │
│ └────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────┘
```

### Componente `Units\InventoryPanel`

Props: `Unit $unit`

Estado:
- Lista de ítems ordenados por `sort_order`, luego `name`
- Modal/formulario para crear y editar (nombre, cantidad, condición select, notas)
- Subida de foto inline por ítem (`wire:model` + `WithFileUploads`)
- Miniaturas clicables → `documents.download` en nueva pestaña
- Empty state con CTA

### Listado de unidades (`Units\Index`)

- Enlace en código de unidad → `properties.units.show`
- Botón secundario "Inventario" en columna acciones (opcional si el código ya enlaza)

### Condición (UI)

| Valor DB | ES | EN |
|----------|----|----|
| `good` | Bueno | Good |
| `fair` | Regular | Fair |
| `poor` | Malo | Poor |

Badge variants: `good` → success, `fair` → warning, `poor` → danger.

## i18n

Nuevo archivo `lang/{es,en}/inventory.php` con claves para títulos, formulario, condiciones, validación, empty state y auditoría.

## Testing

| Test | Archivo |
|------|---------|
| Show page 403 sin `units.view` | `tests/Feature/Units/UnitInventoryShowTest.php` |
| Show page carga ítems scoped a org | mismo |
| CRUD ítem con `units.manage` | `tests/Feature/Units/UnitInventoryPanelTest.php` |
| Subir foto en ítem | mismo |
| Unidad con inventario no eliminable | extender `UnitDeleteTest` |
| Foreign org → 403 | mismo |

Comandos:

```bash
./vendor/bin/sail test --filter=UnitInventory
./vendor/bin/sail pint --dirty
```

## Decisions Log

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Alcance v1 | Solo maestro por unidad | Snapshots por contrato en fase 2 |
| Nombres de ítems | Texto libre | Menos fricción; sin config previa |
| UI | Página de detalle | Escala con ítems/fotos; extensible |
| Fotos | `Document` morph en ítem | Reutiliza storage, download, permisos |
| Permisos | Reutilizar existentes | Sin cambios en seeder v1 |
| Límite fotos | 5 por ítem | Evita abuso; suficiente para evidencia |
| Cierre mensual | No aplica | Inventario no es movimiento financiero |

## Success Criteria

- Ruta `/properties/{property}/units/{unit}` accesible con inventario visible
- CRUD de ítems funcional con validación
- Fotos subibles y descargables por ítem
- Unidad con ítems no eliminable desde listado
- Tests pasan; Pint limpio
- Strings en `lang/es` y `lang/en`
