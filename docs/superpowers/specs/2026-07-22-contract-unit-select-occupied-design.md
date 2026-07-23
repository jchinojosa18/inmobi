# Contract create: exclude occupied units + lock unit/tenant on edit — Design Spec

## Problem

Al crear un contrato, el select de unidades muestra también las que ya tienen contrato `active`. Al editar, unidad e inquilino no deben poder cambiarse.

## Behavior

### Create
- Select de unidades: solo unidades `status=active` **sin** contrato `active`.
- Si se abre con `unitId` precargado y esa unidad está ocupada → no preseleccionar.
- Validación existente de unidad ocupada se mantiene.

### Edit
- Unidad e inquilino: solo lectura (disabled / texto fijo).
- `save()` no reasigna `unit_id` ni `tenant_id` (usa los del contrato existente).
- El select de unidades en edit puede limitarse a la unidad actual (o no listar otras).

## Out of scope
- Cambiar constraint DB / finiquito / inventarios.
- Filtrar inquilinos.

## Approach
Filtro en query de `CreateModal::render` + UI readonly en edit + `save()` ignora cambios de unit/tenant en edit.
