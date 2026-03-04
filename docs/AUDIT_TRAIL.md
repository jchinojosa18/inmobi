# Audit Trail

## Objetivo
Establecer trazabilidad tecnica desde el dia 1 para saber:
- quien realizo un cambio,
- cuando ocurrio,
- que cambio exactamente,
- y cual fue el motivo del ajuste.

## Paquete elegido
Se adopta `spatie/laravel-activitylog` por estas razones:
- Integracion nativa con Eloquent.
- Registro automatico de diff (`old` y `attributes`) sin construir infraestructura custom.
- Soporte directo de `causer` (usuario autenticado) y `subject` (modelo afectado).
- Extensible para agregar metadatos de negocio como `reason`.

## Estructura de datos de auditoria
Las tablas `activity_log` registran:
- `causer_type` y `causer_id`: quien ejecuta la accion.
- `subject_type` y `subject_id`: sobre que entidad se ejecuto.
- `event`: tipo de evento (`created`, `updated`, `deleted`).
- `properties`: JSON con cambios (`old`, `attributes`) y metadatos como `reason`.
- `created_at`: cuando ocurrio el evento.

## Motivo de ajuste
Para operaciones de escritura (`POST`, `PUT`, `PATCH`, `DELETE`) el sistema captura el motivo desde:
1. Campo `audit_reason` en request.
2. Header `X-Audit-Reason`.

El valor se guarda en `activity_log.properties.reason`.

## Implementacion actual (base tecnica)
- `App\Models\Concerns\Auditable`: trait reutilizable para instrumentar modelos.
- `App\Http\Middleware\CaptureAuditReason`: middleware para capturar motivo de ajuste.
- `App\Support\AuditContext`: contexto en memoria por request/ejecucion.
- `App\Models\User`: ejemplo real de entidad ya auditada.

## Convencion para futuras entidades
Cuando se cree una nueva entidad de negocio:
1. Incluir trait `Auditable`.
2. Definir `auditableAttributes()` con campos relevantes.
3. Exigir `audit_reason` en endpoints de ajustes manuales sensibles.

## Notas de uso
- Cambios sin diff no generan registro (se usa `logOnlyDirty` + `dontSubmitEmptyLogs`).
- En procesos de consola sin usuario autenticado, `causer` puede ser `null`.
- Si una operacion requiere motivo obligatorio, validarlo a nivel Request/FormRequest.
