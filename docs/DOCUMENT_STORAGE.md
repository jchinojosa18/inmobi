# Document Storage

## Objetivo
Definir una base de almacenamiento de evidencias/documentos desde el inicio:
- local en desarrollo,
- compatible con S3 en produccion,
- sin exponer credenciales en repositorio.

## Estrategia por entorno

### Desarrollo (local)
- `FILESYSTEM_DISK=local` para uso general de la app.
- `DOCUMENTS_DISK=local` (privado, sin acceso público). Descarga vía ruta autenticada
  `GET /documents/{document}/download` (permiso `documents.view`, valida organización).

### Produccion (S3 compatible)
- Mantener app en `FILESYSTEM_DISK=local` si se desea.
- Cambiar solo documentos a:
  - `DOCUMENTS_DISK=s3`
- Variables requeridas (sin credenciales reales en repo):
  - `AWS_ACCESS_KEY_ID`
  - `AWS_SECRET_ACCESS_KEY`
  - `AWS_DEFAULT_REGION`
  - `AWS_BUCKET`
  - `AWS_ENDPOINT` (para proveedores S3 compatibles)
  - `AWS_URL` (opcional para URL publica custom)
  - `AWS_USE_PATH_STYLE_ENDPOINT` (segun proveedor)

## Flujo actual
- Subida vía `App\Livewire\Documents\Panel`, ligado a un morph allowlist
  (`Contract`, `Payment`, `Expense`, `Unit`, `Charge`) validado contra la organización del usuario.
- Tipos permitidos: `jpg`, `jpeg`, `png`, `pdf`
- Tamano maximo: `5 MB`
- Descarga: `App\Http\Controllers\Documents\DownloadController` vía
  `route('documents.download', $document)`, con permiso `documents.view` y validación de organización.

## Proximos pasos recomendados
- Definir convencion de paths por entidad (`documents/{entidad}/{id}/...`).
- Definir politica de retencion/versionado.
- Integrar escaneo antivirus y/o validacion de contenido cuando aplique.
