# Inventario maestro en casa y local — Design Spec

**Date:** 2026-07-21  
**Status:** Implemented  
**Related:** `docs/superpowers/specs/2026-07-16-unit-inventory-design.md` (cierra la fase 2 de casa/local; terreno sigue fuera)

## Goal

Exponer el **inventario maestro** ya existente en la ficha de **casa** y **local** (`Houses\Show`), reutilizando la `Unit` interna y el componente `Units\InventoryPanel`.

## Out of Scope

- Terreno (`Property::KIND_LAND`): no mostrar panel de inventario
- Nueva tabla o `property_id` en ítems de inventario
- Snapshots de inventario por contrato
- Catálogo de ítems por organización
- Cambios al seeder de permisos / nuevos permisos
- Ruta dedicada de inventario para standalone

## User Stories

1. Como usuario con `properties.view` y `units.view`, quiero ver el inventario maestro al abrir una casa o un local.
2. Como usuario con `units.manage`, quiero CRUD de ítems desde esa misma ficha.
3. Como usuario con permisos de documentos existentes, quiero subir/ver fotos por ítem igual que en departamentos.
4. Como usuario en un terreno, no debo ver sección de inventario.

## Architecture

### Enfoque elegido

Embeber `<livewire:units.inventory-panel :unit="$unit" />` en `resources/views/livewire/houses/show.blade.php` cuando el `Property` sea casa o local.

Relación de datos (sin cambios de esquema):

```text
Property (standalone_house | local)
  └─ Unit (única; kind house | local)
       └─ UnitInventoryItem[]
            └─ Document[] (fotos)
```

`Houses\Show` ya resuelve `$unit = $property->units->first()` en `mount`. Ese mismo `$unit` se pasa al panel.

### Alternativas descartadas

| Enfoque | Motivo |
|---------|--------|
| Ruta `/houses/{property}/inventory` | Navegación extra sin valor; la ficha ya existe |
| Inventario en `Property` (nueva tabla/morph) | Duplica modelo; rompe alineación con contratos/snapshots futuros por unidad |
| Incluir terreno | Fuera de alcance acordado |

## Data Model

Sin migraciones. Sin cambios a `UnitInventoryItem`, `Unit` ni `Document`.

## Routes & Permissions

| Superficie | Comportamiento |
|------------|----------------|
| `GET /houses/{property}` (`houses.show`) | Sin cambio de ruta ni middleware (`properties.view`) |
| Panel embebido | `units.view` para montar; `units.manage` para CRUD; `documents.*` para fotos (lógica actual de `InventoryPanel`) |

Condición de render del panel (Blade):

1. `kind` es `standalone_house` o `local` (no `land`).
2. Además `@can('units.view')`, para que un usuario con solo `properties.view` siga viendo la ficha sin que el `mount` del panel dispare 403.

Terreno: la página sigue funcionando; no se incluye el Livewire del inventario.

## UI

Extender `houses/show.blade.php`:

1. Mantener header + stats actuales.
2. Debajo, si casa o local: inventariar con el mismo panel que `units/show`.
3. Copy: reutilizar `lang/{es,en}/inventory.php` (títulos/empty state del panel). No hace falta archivo i18n nuevo salvo ajustes mínimos de contexto si el copy actual dice solo “unidad/departamento” de forma confusa en esta pantalla — preferir reutilizar; solo tocar strings si el texto resulta incorrecto en casa/local.

## Testing

| Caso | Archivo |
|------|---------|
| Casa: show incluye `InventoryPanel` | `tests/Feature/Houses/StandaloneHouseInventoryTest.php` |
| Local: show incluye `InventoryPanel` | mismo |
| Terreno: show **no** incluye `InventoryPanel` | mismo |
| Sin `units.view`: ficha casa carga, sin panel | mismo (opcional pero recomendado) |
| CRUD/fotos | Cubierto por `UnitInventoryPanelTest` (no duplicar) |

Comandos:

```bash
./vendor/bin/sail test --filter=StandaloneHouseInventory
./vendor/bin/sail pint --dirty
```

## Decisions Log

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Relación | Vía `Unit` interna | Modelo existente; contratos ya usan `unit_id` |
| Alcance | Casa + local | Pedido explícito; terreno excluido |
| Permisos | `units.*` | Mismo panel; roles default ya combinan `properties.view` + `units.view` |
| UI | Embeber en ficha | Menor diff; sin ruta nueva |
| Schema | Sin cambios | Fase 2 del inventario maestro, solo superficie |

## Success Criteria

- En `/houses/{id}` de casa o local se ve y opera el inventario maestro
- En terreno no aparece el panel
- Sin migraciones ni permisos nuevos
- Tests del filtro `StandaloneHouseInventory` en verde; Pint limpio
