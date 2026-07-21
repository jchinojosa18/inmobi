# Sync de códigos de unidad al editar código de edificio — Design Spec

**Date:** 2026-07-21  
**Status:** Approved  
**Related:** `docs/superpowers/specs/2026-06-22-unified-property-create-design.md` (convención `{property.code}-{number}`), `app/Support/UnitNumberingService.php`

## Goal

Al editar el código de un edificio en Properties, actualizar automáticamente los códigos de sus unidades que siguen el patrón `{código_viejo}-{número}`, para que el inventario no quede con prefijos obsoletos.

## Out of Scope (v1)

- Reescribir unidades con códigos custom o sin el prefijo del edificio
- Actualizar `units.name` al cambiar el código del edificio
- Casas/locales standalone (`/houses`)
- Observer en el modelo `Property` (otros call sites no reescriben unidades)
- Acción dedicada tipo `UpdatePropertyCodeAction`
- Confirmación UI / flash con conteo de unidades actualizadas (opcional futuro)

## User Stories

1. Como usuario con `properties.manage`, al cambiar el código de un edificio quiero que las unidades `EDIF-A-101` pasen a `TORRE-B-101` (mismo sufijo, nuevo prefijo).
2. Como usuario con `properties.manage`, no debo poder vaciar el código del edificio si hay unidades con prefijo `{código_actual}-…`.
3. Como usuario con `properties.manage`, unidades con código custom (p. ej. `PENTHOUSE`) no deben modificarse al renombrar el código del edificio.
4. Como usuario con `properties.manage`, editar nombre/dirección/etc. sin tocar el código no debe alterar códigos de unidades.

## Architecture

### Enfoque elegido

Método en `UnitNumberingService` + llamada desde `Properties\Index::save()` dentro de una transacción junto al `update` de la property.

### Alternativas descartadas

| Enfoque | Motivo de descarte |
|---------|-------------------|
| Observer/`updating` en `Property` | Side effects ocultos; el proyecto concentra esta lógica en Support |
| `UpdatePropertyCodeAction` | Overkill para un único call site en Properties Index |

## Behavior

Convención vigente de códigos de unidad: `{property.code}-{number}` (uppercase vía `TextCase`).

En `Properties\Index::save()`:

1. Normalizar campos uppercase (trait existente) y validar como hoy.
2. Cargar property; capturar `$oldCode` y `$newCode` (nullable string tras trim/`?: null`).
3. Si `$oldCode === $newCode` (ambos null o mismo string): update normal de property; no sync.
4. Si `$newCode` es vacío/null y existe al menos una unidad del edificio cuyo `code` empieza con `{oldCode}-`:
   - Bloquear con error de validación en el campo `code`.
   - No persistir cambios.
5. Si el código cambió a un valor no vacío:
   - En `DB::transaction`:
     - Actualizar property (payload completo del form).
     - Llamar `UnitNumberingService::syncUnitCodesAfterPropertyCodeChange(...)`.
6. Si el código se vacía y **no** hay unidades con prefijo `{oldCode}-`: permitir update (property.code = null); no tocar unidades.

### Reglas de sync

- Solo unidades con `property_id` del edificio y `code` que cumple `str_starts_with($code, $oldCode.'-')`.
- Sufijo vía `extractUnitNumber($oldCode, $code)`.
- Nuevo code vía `buildUnitCode($property, $suffix)` (usa el `code` ya actualizado de la property).
- No modificar `name`, floor, ni otros campos.
- Retornar el número de unidades actualizadas (para tests / uso futuro).

## Components

| Pieza | Rol |
|-------|-----|
| `UnitNumberingService::syncUnitCodesAfterPropertyCodeChange(Property $property, ?string $oldCode, ?string $newCode): int` | Reescribe solo unidades `OLD-*` → `NEW-*`; no-op si codes iguales o `oldCode` vacío |
| `UnitNumberingService::propertyHasPrefixedUnits(Property $property, string $propertyCode): bool` | Helper para saber si hay unidades con prefijo `{code}-` (usado por Livewire al validar vaciado) |
| `Properties\Index::save()` | Valida no vaciar code si hay prefijadas; detecta cambio; orquesta transacción property + sync |
| `catalog.validation.property_code_required_with_units` (es/en) | Mensaje de validación al intentar vaciar código con unidades prefijadas |

**Validación de vaciado:** vive en Livewire (`addError('code', …)`), no en el sync. El service solo reescribe cuando `newCode` no está vacío; si se llama con `newCode` vacío, return `0` (defensivo).

Firma sugerida:

```php
public function syncUnitCodesAfterPropertyCodeChange(
    Property $property,
    ?string $oldCode,
    ?string $newCode,
): int

public function propertyHasPrefixedUnits(Property $property, string $propertyCode): bool
```

- Si `oldCode` es null/'' o igual a `newCode`, o `newCode` es null/'': return `0`.
- En caso contrario: update de codes que matchean el prefijo.

## Error handling

- Unique `(property_id, code)`: un rename uniforme de prefijo no debe colisionar; si falla, la transacción hace rollback.
- Códigos ya uppercased antes de comparar (mismo pipeline que create/edit actual).
- Match de prefijo exacto sobre el string almacenado: `{oldCode}-` (case-sensitive tras uppercase).

## Testing

Archivo: `tests/Feature/Properties/PropertyCodeSyncUnitsTest.php`

| Caso | Expectativa |
|------|-------------|
| `EDIF-A` → `TORRE-B` con unidades `EDIF-A-101` y `PENTHOUSE` | `TORRE-B-101`; `PENTHOUSE` intacto; property code `TORRE-B` |
| Vaciar code con unidad `EDIF-A-101` | Error en `code`; property y units sin cambio |
| Update de name sin cambiar code | Units sin cambio |
| Vaciar code sin unidades prefijadas (solo custom o sin units) | Permitido; property.code null |

Usar Livewire test sobre `Properties\Index` + factories existentes (`Property`, `Unit`, org/user con `properties.manage`).

## Acceptance criteria

- [ ] Editar código de edificio reescribe solo unidades con prefijo antiguo.
- [ ] Unidades custom no se modifican.
- [ ] No se puede vaciar el código si hay unidades con ese prefijo.
- [ ] Cambio de otros campos sin tocar code no afecta units.
- [ ] Tests verdes; Pint limpio.
