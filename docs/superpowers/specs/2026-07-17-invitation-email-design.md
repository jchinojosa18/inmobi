# Invitaciones por correo — Design Spec

**Date:** 2026-07-17  
**Status:** Approved  
**Related:** `OrganizationInvitationService`, `InvitationsIndex`, `PaymentReceiptMail`

## Goal

Al crear una invitación desde `/settings/invitations`, el sistema debe:

1. **Validar** que el correo no pertenezca ya a la empresa (usuario existente o invitación pendiente activa).
2. **Enviar un correo** al destinatario con el enlace de aceptación.

## Decisions

| Tema | Decisión |
|------|----------|
| Invitación pendiente existente | **Bloquear** con error en `email` (opción A) |
| Envío de correo | **Síncrono** (`Mail::send`) al crear, patrón `PaymentReceiptMail` |
| Ubicación de reglas | `OrganizationInvitationService::createInvitation()` |
| Link manual en UI | **Eliminar** bloque `lastInvitationLink`; el admin ya no copia el link |
| Idioma del correo | Español (consistente con `payment-receipt.blade.php` v1) |
| Tests | Verificar solo destinatario (`Mail::assertSent` + `hasTo`); no asunto/link |

## Out of Scope

- Reenvío de invitación existente sin crear nueva
- Plantilla configurable por organización
- Cola/queue para el envío
- Traducción EN del correo (fase posterior si se requiere)

## Current State

- `OrganizationInvitationService` ya bloquea si el email pertenece a un `User` de la misma `organization_id`.
- Si existe invitación pendiente para el mismo email, **revoca la anterior** y crea una nueva — esto cambia a **bloquear**.
- `InvitationsIndex` muestra `lastInvitationLink` tras crear; no se envía correo.

## Validation Rules

En `createInvitation()`, antes de insertar:

1. **Usuario en la organización** (ya existe):  
   `User` con `organization_id` + `LOWER(email) = normalizedEmail` → error  
   Mensaje: `__('settings.validation.invitation_email_already_member')`

2. **Invitación pendiente activa** (nuevo):  
   `OrganizationInvitation` con:
   - `organization_id` = target
   - `email` = normalizedEmail
   - `accepted_at IS NULL`
   - `revoked_at IS NULL`
   - `expires_at > now()`  
   → error  
   Mensaje: `__('settings.validation.invitation_email_pending')`

Normalización: `strtolower(trim($email))` (sin cambios).

## Email Flow

```
InvitationsIndex::createInvitation()
  → OrganizationInvitationService::createInvitation()
       → validar usuario / invitación pendiente
       → DB::transaction → insert invitation
       → AuditLogger::log('invitation.created')
       → Mail::to($email)->send(OrganizationInvitationMail)
  → flash success (mensaje actualizado)
```

### `OrganizationInvitationMail`

**Ubicación:** `app/Mail/OrganizationInvitationMail.php`

**Constructor:** recibe datos necesarios para el correo sin exponer el token en el modelo persistido:

- `OrganizationInvitation $invitation` (con `organization` eager-loaded)
- `string $plainToken`
- `?User $invitedBy` (opcional, para personalizar el remitente)

**Asunto:** `Invitación para unirte a {organization_name}`

**Vista:** `resources/views/emails/organization-invitation.blade.php`

**Contenido mínimo:**

- Saludo
- Nombre de la organización
- Rol asignado
- Quién invitó (si hay `invitedBy`)
- Fecha de expiración (timezone `America/Tijuana`)
- Botón/enlace: `route('invitations.accept', ['token' => $plainToken])`
- Nota: el enlace expira según `expires_at`

**Envío:** dentro de `createInvitation()` **después** del transaction exitoso. Si el mail falla, la invitación queda creada (mismo patrón que recibos de pago); el admin ve flash de éxito. No se revierte el insert.

## UI Changes

`InvitationsIndex`:

- Eliminar propiedad `lastInvitationLink` y lógica asociada.
- Mantener flash `settings.flash.invitation_created` (texto puede mencionar que se envió el correo).

`invitations-index.blade.php`:

- Eliminar bloque condicional `@if ($lastInvitationLink)`.

## i18n

Agregar en `lang/es/settings.php` y `lang/en/settings.php`:

```php
'validation' => [
    'invitation_email_already_member' => '...',
    'invitation_email_pending' => '...',
],
'flash' => [
    'invitation_created' => '...', // actualizar para indicar envío por correo
],
```

Reemplazar string hardcodeado en `OrganizationInvitationService` por `__('settings.validation.invitation_email_already_member')`.

## Tests

Archivo: `tests/Feature/Settings/OrganizationInvitationsTest.php`

| Test | Comportamiento |
|------|----------------|
| `test_admin_can_create_invitation_from_settings_screen` | Agregar `Mail::fake()` + `Mail::assertSent(OrganizationInvitationMail::class, fn ($m) => $m->hasTo('nuevo.usuario@test.dev'))` |
| `test_it_blocks_invitation_when_email_already_belongs_to_organization` | Usuario con email en org → `assertHasErrors('email')` + no row nueva |
| `test_it_blocks_invitation_when_pending_invitation_exists` | Crear invitación activa → intentar segunda → `assertHasErrors('email')` + `Mail::assertSentCount(1)` |

No validar asunto, cuerpo ni URL en tests (solo destinatario en el test de creación exitosa).

## Files to Touch

| Archivo | Acción |
|---------|--------|
| `app/Support/OrganizationInvitationService.php` | Validación pendiente, i18n, envío mail |
| `app/Mail/OrganizationInvitationMail.php` | Crear |
| `resources/views/emails/organization-invitation.blade.php` | Crear |
| `app/Livewire/Settings/InvitationsIndex.php` | Quitar `lastInvitationLink` |
| `resources/views/livewire/settings/invitations-index.blade.php` | Quitar bloque link |
| `lang/es/settings.php` | Nuevas claves |
| `lang/en/settings.php` | Nuevas claves |
| `tests/Feature/Settings/OrganizationInvitationsTest.php` | 2 tests nuevos + ajuste existente |

## Security

- El token plano solo viaja por correo y en el return value del service (no se persiste).
- Validaciones en service, no solo en Livewire.
- Sin cambios en RBAC (`invitations.manage`).
