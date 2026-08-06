# Daily Operations Base

## Objetivo
Dejar lista la infraestructura para tareas diarias (multas, recordatorios, cierres) sin implementar todavia la logica de negocio.

## Componentes implementados
- Comando Artisan: `inmo:daily`
  - Clase: `App\Console\Commands\InmoDailyCommand`
  - Estado actual: placeholder, escribe logs y encola un job demo.

- Job en cola: `App\Jobs\DailyOperationsJob`
  - Driver esperado: Redis (`QUEUE_CONNECTION=redis`).
  - Estado actual: placeholder, escribe logs al ejecutarse.

- Scheduler:
  - Definicion en `routes/console.php`.
  - Frecuencia actual: diario a las `00:05`.
  - Proteccion de solapamiento: `withoutOverlapping()`.

## Desarrollo con Sail
Ejecutar en terminales separadas:

```bash
./vendor/bin/sail artisan schedule:work
```

```bash
./vendor/bin/sail artisan queue:work redis --queue=default --tries=3
```

Comando manual para pruebas:

```bash
./vendor/bin/sail artisan inmo:daily
```

## Produccion
Cron del sistema (cada minuto):

```cron
* * * * * cd /var/www/inmo-admin && php artisan schedule:run >> /dev/null 2>&1
```

Worker de cola recomendado bajo Supervisor/Systemd:

```bash
php artisan queue:work redis --queue=default --sleep=1 --tries=3 --max-time=3600
```

## Scope actual
- No hay calculo de multas.
- No hay logica de recordatorios.
- No hay proceso real de cierres.
- Solo infraestructura operativa base.
