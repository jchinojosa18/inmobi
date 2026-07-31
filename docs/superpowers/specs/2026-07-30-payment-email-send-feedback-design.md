# Payment email send feedback — design

Fecha: 2026-07-30  
Pantalla: `/payments/{id}` (`App\Livewire\Payments\Show`)

## Problema

Al enviar el recibo por correo no hay feedback de “en proceso”. El toast de éxito usa un texto largo orientado a desarrollo («Recibo enviado por correo (revisa Mailpit…)») en lugar de un mensaje corto de confirmación.

## Objetivo

1. Mientras corre `sendEmail`, mostrar en el toast de la esquina inferior derecha una **barra de progreso indeterminada**.
2. Al terminar con éxito, reemplazar esa barra por el texto **«Mensaje Enviado»** y ocultar el toast ~2.5s (comportamiento actual de auto-hide).
3. Evitar doble envío deshabilitando el botón durante la request.

## Comportamiento

| Momento | UI |
|---|---|
| Idle | Toast oculto; botón habilitado si el inquilino tiene email |
| Click «Enviar por email» | Toast visible con progress bar indeterminada; botón `disabled` |
| Éxito (`payment-receipt-email-sent`) | Progress bar → texto «Mensaje Enviado»; auto-hide ~2.5s |
| Error (sin email del tenant) | Sin toast de éxito; se mantiene `@error('emailRecipient')` bajo el input |

## Enfoque técnico

Reutilizar el toast Alpine existente en `resources/views/livewire/payments/show.blade.php`.

- Estado Alpine: `status: null | 'sending' | 'sent'`.
- Al iniciar la acción Livewire (`wire:click="sendEmail"` / loading target `sendEmail`): `status = 'sending'`, toast visible.
- En `payment-receipt-email-sent.window`: `status = 'sent'`; timer de 2.5s → ocultar y reset.
- Botón: `wire:loading.attr="disabled"` + `wire:target="sendEmail"` (y opcional clase de opacidad).
- Progress bar: CSS/Tailwind indeterminada (track + barra animada), sin porcentaje real (el envío es síncrono y no reporta progreso).
- i18n: key corta para éxito, p.ej. `finance.flash.message_sent` → ES «Mensaje Enviado» / EN «Message Sent». El toast de esta pantalla deja de usar `finance.flash.receipt_sent`.
- `Show::sendEmail()`: conservar envío y `dispatch('payment-receipt-email-sent')`. Alinear `session()->flash('success', …)` al mismo texto corto si el flash global se muestra en layout; si no se usa en esta pantalla, puede quedar o actualizarse a la misma key (sin ampliar alcance).

## Fuera de alcance

- Cola asíncrona / jobs de correo.
- Cambiar destinatario o contenido del mail.
- Progress bar determinista (%).
- Otros flujos de envío (p.ej. checkbox en registro rápido de pago).

## Verificación

- `./vendor/bin/sail test --filter=PaymentShowEmailTest`
- `./vendor/bin/sail pint --dirty`
- Smoke manual: click enviar → barra en esquina → «Mensaje Enviado»; sin email de tenant → error, sin toast de éxito.
