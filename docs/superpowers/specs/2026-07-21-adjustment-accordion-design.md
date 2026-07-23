# Crear ajuste — acordeón — Design Spec

**Date:** 2026-07-21  
**Status:** Approved  
**Related:** `resources/views/livewire/contracts/show.blade.php`, deposit/settlement accordion specs

## Goal

En `contracts/{id}`, el card **Crear ajuste** debe colapsarse con acordeón para ahorrar espacio. Header: solo título + chevron. Empieza cerrado.

## Out of Scope

- Resumen en header
- Persistencia de abierta/cerrada
- Cambios a `createAdjustment` / validaciones / Actions
- Componente UI genérico

## Behavior

| Item | Valor |
|------|--------|
| Estado inicial | `open: false` |
| Header | `contracts.create_adjustment` + chevron |
| Cuerpo | Descripción + formulario de ajuste (sin cambios internos) |

Alpine en la Blade del card; sin propiedad Livewire para el toggle.

## i18n

- ES: `adjustment_panel_toggle` → «Mostrar u ocultar crear ajuste»
- EN: «Show or hide create adjustment»

## Testing

Feature test (Livewire `Contracts\Show` o aserción en vista):

- `x-data="{ open: false }"`
- `aria-expanded="false"`
- Ve el título `create_adjustment`

## Verification

```bash
./vendor/bin/sail test --filter=ContractShow
./vendor/bin/sail pint --dirty
```
