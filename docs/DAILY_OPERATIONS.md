# Daily Operations

## Objetivo
Pulse de salud del pipeline diario + comandos de negocio dedicados (multas, rentas, backup).

## Componentes
- Comando Artisan: `inmo:daily`
  - Clase: `App\Console\Commands\InmoDailyCommand`
  - Encola `App\Jobs\DailyOperationsJob`, que escribe heartbeat `daily_operations` (no lógica financiera).

- Negocio (schedules propios en `routes/console.php`):
  - `inmo:penalties:run` — 00:05 America/Tijuana
  - `inmo:generate-rent` — 00:10 America/Tijuana
  - `inmo:backup` — 03:10 America/Tijuana

- Scheduler heartbeat: cada minuto (`system:heartbeat:scheduler`).

## Desarrollo con Sail

```bash
./vendor/bin/sail artisan schedule:work
```

```bash
./vendor/bin/sail artisan queue:work redis --queue=default --tries=3
```

```bash
./vendor/bin/sail artisan inmo:daily
```

## Producción
Cron del sistema (cada minuto):

```cron
* * * * * cd /var/www/inmo-admin && php artisan schedule:run >> /dev/null 2>&1
```

Worker de cola recomendado bajo Supervisor/Systemd:

```bash
php artisan queue:work redis --queue=default --sleep=1 --tries=3 --max-time=3600
```

## Scope
- `inmo:daily` = heartbeat operativo.
- Multas / rentas / backups = comandos dedicados (no viven dentro del job daily).
