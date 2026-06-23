# Unified Property Create Flow — Design Spec

**Date:** 2026-06-22  
**Status:** Approved

## Goal

Unify "Nuevo edificio/propiedad" and "Nueva casa" into a single **Nuevo inmueble** entry point. From there the user selects the entity type and follows the appropriate creation flow.

## Domain Rules

| Type | Behavior | Property `kind` | Unit `kind` | Units |
|------|----------|-----------------|-------------|-------|
| Casa | Single entity, one-step create | `standalone_house` | `house` | 1 auto |
| Local | Single entity, one-step create | `local` | `local` | 1 auto |
| Terreno | Single entity, one-step create | `land` | `land` | 1 auto |
| Edificio | Two-step: property then bulk units | `building` | `apartment` | Bulk wizard on units screen |

### Building unit numbering

Pattern `{floor}{unitIndex:02d}`:
- Floor 1, 4 units → `101`, `102`, `103`, `104`
- Floor 2, 3 units → `201`, `202`, `203`
- Floor 10, 2 units → `1001`, `1002`

Final unit code: `{property.code}-{number}` (existing convention).

## UX Flow

### Entry

Single button **Nuevo inmueble** on `/properties` opens unified modal.

### Step 1 — Type picker

Four cards: Casa, Edificio, Local, Terreno.

### Step 2 — Form

**Casa / Local / Terreno:** name*, address, notes → save creates Property + Unit in transaction.

**Edificio:** name*, code*, status, address, notes → save creates Property → redirect to `/properties/{id}/units?bulk=1`.

### Bulk unit wizard (units screen)

- Button **Generar unidades** for buildings.
- Dynamic rows: floor number + unit count per floor.
- Preview before confirm.
- Auto-opens when `?bulk=1` after building create.

## Data Model

No migration. Add string constants:

```php
Property::KIND_LOCAL = 'local'
Property::KIND_LAND  = 'land'
Unit::KIND_LOCAL     = 'local'
Unit::KIND_LAND      = 'land'
```

Add `Property::isStandaloneEntity()` (casa | local | terreno).

## Components

| Component | Role |
|-----------|------|
| `Properties\CreateModal` | Unified type picker + create forms (replaces `Houses\CreateModal`) |
| `Properties\Index` | Single button; edit-only inline modal |
| `Units\Index` | Bulk generate modal + `?bulk=1` auto-open |
| `Houses\Show` | Generalized for all standalone entity types |

## Backward Compatibility

- `houses.create` route redirects to `properties.index?create=1`
- `create_house=1` query param still opens create modal
- `houses.show` works for casa, local, terreno

## Out of Scope

- Separate detail routes per standalone type
- Property subtype migration / enum column
- Editing property kind after creation

## Tests

- Create modal: each type creates correct Property + Unit kinds
- Building create redirects with `bulk=1`
- Bulk generator: correct codes and floor assignment
- Standalone show page accepts local/land
- Units index redirects standalone entities to show page
