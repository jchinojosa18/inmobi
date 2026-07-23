# Finiquito de contrato — acordeón — Design Spec

**Date:** 2026-07-21  
**Status:** Approved  
**Related:** `resources/views/livewire/contracts/settlement-wizard.blade.php`, `docs/superpowers/specs/2026-07-21-deposit-hold-accordion-design.md`

## Goal

En `contracts/{id}`, el card **Finiquito de contrato** debe poder abrirse y cerrarse (acordeón) para no ocupar espacio vertical cuando no se necesita, sin cambiar la lógica de finiquito ni el refresh de totales por eventos de depósito.

## Out of Scope

- Componente UI genérico reutilizable
- Persistencia de abierta/cerrada entre visitas
- Cambios en `ProcessContractSettlementAction` o validaciones
- Cambiar el comportamiento de refresh por `deposit-hold-*` (ya especificado aparte)

## User Stories

1. Como usuario, quiero que el finiquito llegue **cerrado** al abrir el contrato, para priorizar otros bloques.
2. Como usuario, quiero ver de un vistazo las 5 cifras del depósito/adeudo en el encabezado sin expandir.
3. Como usuario, quiero expandir el card para capturar conceptos de salida o ver el PDF del último finiquito.

## Behavior

### Estado inicial

Siempre **cerrado** al cargar (`open: false`), independientemente de si el contrato está activo o `ended`.

Tras un re-render Livewire (p. ej. evento `deposit-hold-registered`), Alpine reinicializa `open` a `false`. Es aceptable: el header sigue mostrando totales actualizados; el usuario reabre si está editando el formulario. (No se añade propiedad Livewire para recordar `open`.)

### Header (siempre visible)

Click en todo el header alterna abierto/cerrado.

Contenido:

- Título: `contracts.settlement_title`
- Chevron con rotación + `aria-expanded` / `aria-controls`
- Resumen con las **5 cifras** (mismas claves/valores que hoy):
  - Depósito registrado (`deposit_paid` / `$paidDeposit`)
  - Depósito aplicado (`deposit_applied` / `$appliedDeposit`)
  - Depósito devuelto (`deposit_refunded` / `$refundedDeposit`)
  - Disponible (`available` / `$availableDeposit`)
  - Adeudo actual (`current_outstanding` / `$currentOutstanding`)

Layout del resumen en header: compacto (p. ej. grid o lista tipográfica pequeña a la derecha / debajo del título en móvil), reutilizando el bloque visual actual del summary box o una variante más densa. No duplicar el summary box también dentro del cuerpo.

La descripción (`settlement_description`) **no** va en el header; queda en el cuerpo.

### Cuerpo (visible solo si abierto)

1. Descripción
2. Errores `settlement_general`
3. Formulario de finiquito **o** mensaje `settlement_ended_blocked` si `$isEnded`
4. Banner de último PDF / summary si existe (`$lastSettlementPdfUrl`)

## Architecture

### Enfoque elegido

Alpine.js en `settlement-wizard.blade.php`:

```blade
x-data="{ open: false }"
```

- Header: `<button type="button">` con toggle, a11y labels
- Cuerpo: `x-show="open"` + `x-cloak`
- Sin propiedad PHP en `SettlementWizard` para el acordeón

Patrón alineado con `deposit-hold-form.blade.php`.

### Alternativas descartadas

| Enfoque | Motivo |
|---------|--------|
| Livewire `$panelOpen` | Round-trips innecesarios |
| `x-ui.collapsible-card` genérico | YAGNI |
| Abierto si activo / cerrado si ended | Usuario eligió siempre cerrado |

## i18n

Agregar clave a11y, p. ej.:

- ES: `settlement_panel_toggle` → «Mostrar u ocultar finiquito de contrato»
- EN: «Show or hide contract settlement»

Reutilizar labels existentes de las 5 cifras.

## Testing

Feature tests Livewire sobre `SettlementWizard`:

1. Markup inicial: `x-data="{ open: false }"`, `aria-expanded="false"`.
2. Header muestra las 5 etiquetas (`deposit_paid`, etc.) incluso con panel cerrado (están fuera de `x-show`).
3. Descripción / CTA de confirmar finiquito viven en el cuerpo (`x-show`); no hace falta test de clic Alpine en PHPUnit.

Actualizar tests existentes de refresh de depósito si asumen estructura antigua pero siguen viendo montos/labels en el HTML (deben seguir pasando porque el summary queda en el header).

## Verification

```bash
./vendor/bin/sail test --filter=SettlementWizard
./vendor/bin/sail pint --dirty
```

## Risks / Notes

- Al registrar un depósito, el re-render cierra el acordeón si estaba abierto. Mitigación aceptada (YAGNI de persistir `open` en Livewire). Si molesta en uso real, fase 2: `wire:ignore` + Alpine localStorage o estado Livewire.
- Los 5 montos del header se actualizan con el refresh por eventos de depósito (listeners ya existentes), aunque el panel esté cerrado.
