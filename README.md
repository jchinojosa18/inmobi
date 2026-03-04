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

## Base operativa diaria (placeholder)
- Comando diario: `inmo:daily` (solo logs + dispatch de job de ejemplo)
- Job ejemplo: `App\\Jobs\\DailyOperationsJob` en cola `default`
- Programación: scheduler diario a las `00:05` (ver `routes/console.php`)

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
