# inmo-admin

SaaS de administracion inmobiliaria basado en Laravel 11.

## Stack
- Laravel 11
- Livewire
- Tailwind CSS
- Docker (Laravel Sail)
- MySQL, Redis, Mailpit

## Requisitos
- Docker Desktop (Mac)
- Docker Compose v2
- Git

## Version de PHP
- Desarrollo (Sail): PHP 8.3
- Produccion recomendada: PHP 8.2 o PHP 8.3

## Comandos de arranque

Levantar servicios:
```bash
./vendor/bin/sail up -d
```

Instalar dependencias frontend:
```bash
./vendor/bin/sail npm install
```

Correr Vite:
```bash
./vendor/bin/sail npm run dev
```

Ejecutar migraciones:
```bash
./vendor/bin/sail artisan migrate
```

Ejecutar scheduler en desarrollo (terminal dedicada):
```bash
./vendor/bin/sail artisan schedule:work
```

Ejecutar worker de cola Redis en desarrollo (terminal dedicada):
```bash
./vendor/bin/sail artisan queue:work redis --queue=default --tries=3
```

Ejecutar tests:
```bash
./vendor/bin/sail test
```

Ejecutar formateo (Pint):
```bash
./vendor/bin/sail pint
```

Crear enlace simbolico para archivos publicos (una vez por entorno):
```bash
./vendor/bin/sail artisan storage:link
```

Nota: equivalente sin Sail:
```bash
php artisan storage:link
```

## Variables importantes de entorno
`.env` configurado para Sail:

```env
APP_NAME="inmo-admin"
DB_HOST=mysql
DB_DATABASE=inmo
DB_USERNAME=sail
DB_PASSWORD=password
FILESYSTEM_DISK=local
DOCUMENTS_DISK=public
QUEUE_CONNECTION=redis
REDIS_HOST=redis
```

## Demo de carga de documentos
- Ruta demo: `/demo/document-upload`
- Flujo actual:
  - Valida tipo de archivo (`jpg`, `jpeg`, `png`, `pdf`)
  - Valida tamano maximo (5 MB)
  - Guarda en `storage/app/public/documents/demo` usando disk `DOCUMENTS_DISK`
  - Muestra link de descarga al archivo guardado

## MVP de pagos y recibos
- Registrar pago desde detalle de contrato: boton `Registrar pago`.
- Flujo MVP al guardar:
  - crea `Payment`
  - genera `receipt_folio` por configuracion de organizacion (`/settings`)
    - default compatible: `REC-YYYY-######`
  - aplica allocations con prioridad definida en `ApplyPaymentAction`
  - genera PDF con desglose real de allocations
  - permite enviar por email (Mailpit en dev) y abrir WhatsApp via deep link `wa.me`
- Ruta de recibo interna (auth): `/payments/{paymentId}/receipt.pdf`
- Ruta compartible firmada (7 dias): `/receipts/{paymentId}/shared.pdf`

## Configuracion por organizacion
- Ruta: `/settings` (auth).
- Edicion: solo rol `Admin`.
- Ajustes disponibles:
  - Folio de recibo: modo (`annual` o `continuous`), prefijo opcional, padding.
  - Plantillas de mensaje: WhatsApp y email.
  - Categorias de egresos (CRUD simple).
- Variables para plantillas:
  - `{tenant_name}`, `{unit_name}`, `{amount_due}`, `{shared_receipt_url}`
- Compatibilidad:
  - Si no hay registro de settings, se usan defaults actuales para no romper flujos existentes.

## Depósitos y finiquito (MVP)
- Depósito garantía:
  - Registrar en detalle de contrato como `DEPOSIT_HOLD` (cargo de depósito recibido).
  - Puede pagarse con flujo normal de pagos para conservar evidencia/recibo.
  - `DEPOSIT_HOLD` se excluye de ingresos operativos.
- Finiquito:
  - Desde detalle de contrato (`/contracts/{id}`), wizard con fecha salida + conceptos + evidencia opcional.
  - Crea cargos `MOVEOUT`.
  - Aplica depósito disponible con `DEPOSIT_APPLY` (crédito negativo).
  - Si sobra depósito, crea `Expense` categoría `Refund deposit`.
  - Cierra contrato (`status=ended`, `ends_at` fecha salida).
  - PDF: `/contracts/{contract}/settlements/{batch}/pdf`

## MVP de egresos y reporte de flujo
- Egresos:
  - Ruta: `/expenses`
  - Incluye alta y listado con filtros por fecha, unidad y categoría.
- Reporte `Flujo por rango`:
  - Ruta: `/reports/flow`
  - Ingresos operativos: suma de `PaymentAllocation.amount` por fecha de pago en el rango.
  - Tipos incluidos (configurable): `RENT`, `PENALTY`, `SERVICE`, `OTHER`, `ADJUSTMENT`.
  - Tipos excluidos: `DEPOSIT_HOLD`, `DEPOSIT_APPLY`.
  - Egresos: suma de `Expense.amount` en el rango.
  - Neto: `Ingresos - Egresos`.
  - Incluye detalle de ingresos (allocations), detalle de egresos y exportación CSV con totales (`/reports/flow/export.csv`).

## Cierres mensuales
- Ruta: `/month-closes` (requiere auth).
- Permite cerrar mes (`YYYY-MM`) con snapshot financiero y bloqueo de escrituras retroactivas.
- Reapertura de mes: solo rol `Admin`.
- Entidades bloqueadas en mes cerrado:
  - `Payments` por `paid_at`
  - `Expenses` por `spent_at`
  - `Charges` por `charge_date/period`
  - `Documents` vinculados a `Payment/Expense/Charge` del mes
- Correcciones en mes cerrado:
  - usar `Charge` tipo `ADJUSTMENT` con `meta.reason` obligatorio.

## Base operativa diaria (placeholder)
- Comando diario: `inmo:daily` (solo logs + dispatch de job de ejemplo)
- Job ejemplo: `App\\Jobs\\DailyOperationsJob` en cola `default`
- Programación: scheduler diario a las `00:05` (ver `routes/console.php`)
- Multas diarias:
  - `php artisan inmo:penalties:run --date=YYYY-MM-DD`
  - opcional para backfill controlado: `--from-date=YYYY-MM-DD`
- Robustez de ejecución:
  - `inmo:generate-rent`, `inmo:penalties:run` e `inmo:daily` usan mutex por cache (`Cache::lock`).
  - Si el lock está tomado, el comando sale con `exit code 0` y mensaje `skipped (locked)`.

## Herramientas internas de operación
- Panel técnico Admin-only: `/admin/system`
  - Muestra `APP_ENV`, `APP_DEBUG`, versión de PHP (sin secretos)
  - Salud de DB, Redis, Storage (writable + `storage:link`)
  - Estado de scheduler, queue worker y backups usando `system_heartbeats`
- Backups:
  - Comando: `php artisan inmo:backup --keep=14`
  - Incluye backup DB + `documents.zip` en `storage/app/backups/<timestamp>/`
  - Rotación simple por cantidad de snapshots (`--keep`)

## Smoke test E2E (sin UI)
- Seeder demo:
  - `php artisan db:seed --class=DemoDataSeeder`
- Comando smoke:
  - `php artisan inmo:smoke --date=2026-03-10`
- El comando valida flujo integral: rentas, multas, pago parcial, cierre de mes anterior, finiquito y resumen de totales.

Producción (cron + worker):
```cron
* * * * * cd /var/www/inmo-admin && php artisan schedule:run >> /dev/null 2>&1
```

Worker recomendado en producción (supervisor/systemd):
```bash
php artisan queue:work redis --queue=default --sleep=1 --tries=3 --max-time=3600
```

## Repo health check (2026-03-04)

Comandos ejecutados en Sail:
```bash
./vendor/bin/sail up -d
./vendor/bin/sail composer install
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
./vendor/bin/sail artisan migrate:fresh --force
./vendor/bin/sail test
./vendor/bin/sail pint --test
```

Ajustes realizados durante el check:
- `compose.yaml`: runtime de Sail ajustado a PHP `8.3` (`runtimes/8.3`, imagen `sail-8.3/app`) para evitar deprecations de PHP 8.5 en tests.
- `config/database.php`: compatibilidad para atributo SSL de MySQL (`Pdo\\Mysql::ATTR_SSL_CA` / `PDO::MYSQL_ATTR_SSL_CA`).
- `composer.lock`: dependencias Symfony fijadas en rama `7.4` para compatibilidad con PHP 8.3 (`clock`, `css-selector`, `event-dispatcher`, `string`, `translation`).

Como validar que el repo está "verde":
```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate:fresh --force
./vendor/bin/sail test
./vendor/bin/sail pint --test
```

Resultado esperado:
- `Tests: ... passed`
- `PASS ... Laravel Pint`

## Deployment (placeholder)
Pendiente por definir pipeline, estrategia de release y monitoreo.
