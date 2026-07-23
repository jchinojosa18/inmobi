# Depósito recibido — acordeón en contrato — Design Spec

**Date:** 2026-07-21  
**Status:** Approved  
**Related:** `resources/views/livewire/contracts/deposit-hold-form.blade.php`, `app/Livewire/Contracts/DepositHoldForm.php`

## Goal

En `contracts/{id}`, el card **Depósito recibido** debe poder abrirse y cerrarse (acordeón) para no ocupar espacio vertical cuando no se necesita, sin cambiar la lógica financiera de registro/anulación de depósitos.

## Out of Scope

- Componente UI genérico reutilizable (`x-ui.collapsible-card`)
- Persistencia de preferencia abierta/cerrada entre visitas (localStorage / backend)
- Cambios en Actions, permisos, PDF de recibo o balance de depósito
- Aplicar el mismo patrón a otros cards del show (ajustes, settlement, etc.)

## User Stories

1. Como usuario con `charges.manage`, quiero colapsar el card de depósito cuando ya no lo estoy usando, para ver más contenido del contrato.
2. Como usuario con depósito pendiente, quiero que el card abra expandido al cargar la página, para registrar o revisar sin un clic extra.
3. Como usuario con depósito completo, quiero que el card llegue cerrado, con un indicador claro de «Depósito completo», y poder abrirlo si necesito el historial o el PDF.

## Behavior

### Estado inicial

| Condición | Estado al cargar |
|-----------|------------------|
| `remainingDeposit > 0` | Abierto |
| `remainingDeposit <= 0` | Cerrado |

El estado se calcula en el cliente a partir de `$remainingDeposit` ya disponible en la vista. Un toggle manual del usuario durante la visita no se sincroniza con el servidor; al recargar se vuelve a aplicar la regla anterior.

### Header (siempre visible)

Click en todo el header (título + resumen + chevron) alterna abierto/cerrado.

Contenido del header:

- Título: `contracts.deposit_received`
- Chevron que rota según estado (`aria-expanded`)
- Resumen a la derecha del título:
  - Si hay pendiente (`remainingDeposit > 0`): montos **registrado / restante** (ej. `$650.00 / $350.00`)
  - Si está completo (`remainingDeposit <= 0`): texto **Depósito completo** en verde (estilo alineado al banner emerald existente del card)

La descripción larga (`deposit_received_description`) **no** va en el header; queda dentro del cuerpo expandido.

### Cuerpo (visible solo si abierto)

Igual que hoy:

1. Descripción
2. Grid de 3 stats (contrato / registrado / restante)
3. Tabla de holds (si hay)
4. Banner «completo» o formulario de registro
5. Modal de anulación (sigue montado; no depende del acordeón)

## Architecture

### Enfoque elegido

Alpine.js en `deposit-hold-form.blade.php`:

```blade
x-data="{ open: {{ $remainingDeposit > 0 ? 'true' : 'false' }} }"
```

- Header: `<button type="button">` o elemento con rol de botón, `@click="open = !open"`, `aria-expanded`, `aria-controls`
- Cuerpo: `x-show="open"` (sin persistir en Livewire)

No se agrega propiedad PHP al componente Livewire: el toggle es puramente de presentación.

### Alternativas descartadas

| Enfoque | Motivo de descarte |
|---------|-------------------|
| Propiedad Livewire `$panelOpen` | Round-trips innecesarios; no hay lógica de servidor |
| Componente `x-ui.collapsible-card` | Alcance mayor al pedido; YAGNI para un solo card |

## i18n

- Reutilizar `contracts.deposit_complete_title` («Depósito completo» / equivalente EN) para el badge del header cuando `remainingDeposit <= 0`.
- Si hace falta etiqueta de accesibilidad del toggle (ej. «Mostrar / ocultar depósito»), agregar claves en `lang/{es,en}/contracts.php`. No inventar strings hardcodeados.

## Testing

- Feature test existente de `DepositHoldForm`: asegurar que el markup del card sigue renderizando título y contenido (formulario o banner completo según estado).
- Añadir aserciones mínimas de presencia del toggle / `aria-expanded` / texto de resumen en header:
  - Contrato con restante > 0: header muestra montos registrado/restante; `aria-expanded="true"` (o equivalente en HTML inicial).
  - Contrato con restante = 0: header muestra «Depósito completo»; `aria-expanded="false"`.
- No se requieren tests de interacción Alpine (JS) en PHPUnit.

## Verification

```bash
./vendor/bin/sail test --filter=DepositHoldForm
./vendor/bin/sail pint --dirty
```

## Risks / Notes

- Tras registrar el último tramo de depósito, Livewire re-renderiza la vista: Alpine reinicializa `open` a `false` (depósito completo). Es el comportamiento deseado.
- Tras anular un hold y volver a haber restante, el re-render deja `open = true`. También deseado.
- El modal de void usa estado Livewire; debe permanecer fuera o independiente del `x-show` del cuerpo para no romper el confirm si el usuario colapsa el card con el modal abierto (preferencia: modal fuera del bloque `x-show`).
