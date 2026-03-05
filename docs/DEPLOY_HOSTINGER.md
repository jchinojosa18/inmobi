# Deployment en Hostinger (Guia General)

## Objetivo
Documentar un proceso base de deployment para `inmo-admin` en Hostinger bajo dos escenarios:
- A) Web Hosting (sin Docker)
- B) VPS (con Docker)

Esta guia evita supuestos de plan especifico. Valida siempre capacidades reales del plan antes de ejecutar.

## Checklist previo (aplica a ambos escenarios)
- [ ] Respaldar base de datos y archivos antes de cada release.
- [ ] Confirmar version de PHP compatible con Laravel 11.
- [ ] Confirmar acceso SSH (si se usaran comandos en servidor).
- [ ] Confirmar variables de entorno de produccion (`.env`) completas.
- [ ] Definir ventana de mantenimiento para migraciones.
- [ ] Ejecutar tests y lint en CI antes de desplegar.

---

## Escenario A: Hostinger Web Hosting (sin Docker)

### Flujo recomendado
1. Preparar release local (desde tu Mac):
```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

2. Empaquetar y subir (SFTP/Git/File Manager):
- Código Laravel completo.
- `vendor/` si el servidor no permite correr Composer.
- `public/build/` generado en el paso anterior.
- `storage/app/public` si necesitas preservar archivos locales ya generados.

3. Configurar el document root del dominio para apuntar a `.../public`.

4. Configurar variables de entorno en `.env` (produccion):
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=...`
- Credenciales reales de base de datos
- Configuracion de correo
- Configuracion de cache/queue segun disponibilidad del plan
- `FILESYSTEM_DISK=public` o la estrategia definida para produccion

5. Instalar dependencias PHP en servidor (si hay SSH y Composer disponible):
```bash
composer install --no-dev --optimize-autoloader
```

6. Inicializar app:
```bash
php artisan key:generate
php artisan storage:link
```

7. Warmup/cachés recomendadas:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

8. Ejecutar migraciones:
```bash
php artisan migrate --force
```

9. Ajustar permisos de escritura en:
- `storage/`
- `bootstrap/cache/`

Permisos comunes:
- Directorios: `775`
- Archivos: `664`

10. Configurar cron del scheduler (hPanel Cron Jobs):
```cron
* * * * * /usr/bin/php /home/USUARIO_DOMINIO/public_html/artisan schedule:run >> /dev/null 2>&1
```

11. Backups (cron diario sugerido):
```cron
10 3 * * * /usr/bin/php /home/USUARIO_DOMINIO/public_html/artisan inmo:backup >> /home/USUARIO_DOMINIO/logs/inmo-backup.log 2>&1
```

12. Prune de backups (cron mensual sugerido):
```cron
35 3 1 * * /usr/bin/php /home/USUARIO_DOMINIO/public_html/artisan inmo:backup:prune --force --yes >> /home/USUARIO_DOMINIO/logs/inmo-backup-prune.log 2>&1
```

12. Queue worker:
- En Web Hosting compartido normalmente no hay `supervisor` ni procesos permanentes.
- Si no hay procesos residentes, usa jobs `sync` o verifica si tu plan permite comandos persistentes.

### Notas operativas
- Si el plan no permite procesos en segundo plano, evita depender de workers persistentes.
- Scheduler en hosting compartido normalmente se ejecuta por Cron.
- Si no hay build frontend en servidor, siempre sube `public/build` desde local o CI.

### Checklist de salida (Web Hosting)
- [ ] Dominio responde y carga la app.
- [ ] `APP_DEBUG=false`.
- [ ] Migraciones aplicadas sin errores.
- [ ] `storage:link` activo y accesible.
- [ ] Subida/descarga de documentos validada.
- [ ] Logs de Laravel revisados post-release.
- [ ] Cron configurado para scheduler (si aplica).
- [ ] `inmo:backup` ejecuta y genera snapshot en `storage/app/backups`.
- [ ] `/admin/system` muestra DB/Redis/Storage/Scheduler en estado saludable.

---

## Escenario B: Hostinger VPS (con Docker)

### Flujo recomendado
1. Preparar servidor VPS:
- Instalar Docker Engine y Docker Compose plugin.
- Configurar firewall (abrir al menos `80` y `443`; restringir accesos administrativos).
- Crear usuario no root para operaciones de despliegue.

2. Subir/clonar proyecto en VPS.

3. Preparar `.env` de produccion (sin credenciales en repositorio):
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=...`
- DB/Redis/Mail con valores productivos
- Configuracion de disco para documentos (`S3` compatible o estrategia local definida)

4. Levantar stack con Docker Compose:
```bash
docker compose up -d --build
```

5. Build de assets en release (opción recomendada dentro de contenedor app):
```bash
docker compose exec app npm ci
docker compose exec app npm run build
```

6. Ejecutar tareas de bootstrap dentro del contenedor de app:
```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan storage:link
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

7. Configurar reverse proxy (Nginx):
- Escuchar en `80/443`.
- Apuntar al servicio PHP/Laravel correspondiente.
- Servir archivos estaticos y enrutar dinamicos a la app.
- Configurar headers de proxy (`X-Forwarded-*`) y limites de upload.

8. SSL (notas generales):
- Usar certificados validos (ejemplo: Let's Encrypt).
- Configurar renovacion automatica del certificado.
- Forzar redireccion HTTP -> HTTPS.

9. Scheduler y queue en contenedores dedicados o procesos supervisados:
- Scheduler: `php artisan schedule:work` o cron `schedule:run`.
- Queue worker: `php artisan queue:work redis ...`

Supervisor (ejemplo orientativo para queue worker):
```ini
[program:inmo-queue]
command=/usr/bin/php /var/www/html/artisan queue:work redis --queue=default --sleep=1 --tries=3 --max-time=3600
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/inmo-queue.log
user=www-data
```

10. Backups:
- Base de datos: dumps periodicos + retencion definida.
- Archivos: `storage/` y/o bucket de documentos.
- Infraestructura: respaldo de volumenes y versionado de configuraciones.
- Comando app:
```bash
docker compose exec app php artisan inmo:backup --keep=14
```

### Checklist de salida (VPS Docker)
- [ ] Contenedores `up` y saludables.
- [ ] Reverse proxy funcionando en dominio productivo.
- [ ] HTTPS activo con renovacion prevista.
- [ ] Migraciones aplicadas.
- [ ] Scheduler y queue operando.
- [ ] Politica de backups documentada y probada (restore test).
- [ ] Monitoreo/logs minimos activos (app + proxy + DB).
- [ ] `/admin/system` validado por usuario Admin.

---

## Trusted Proxies (IP real detrás de proxy)

Si el servidor está detrás de un reverse proxy (Nginx, Cloudflare, etc.) la IP de `request()->ip()` puede devolver la IP del proxy en lugar de la del cliente real.

Laravel 11 incluye `TrustProxies` por defecto. Para configurarlo, agrega en `.env`:

```env
# Confiar en todos los proxies (apto si el servidor está completamente atrás de proxy/CDN)
TRUST_PROXIES=*

# O listar IPs/rangos específicos de tu proxy
TRUST_PROXIES=103.21.244.0/22,103.22.200.0/22
```

Laravel 11 lee `TRUST_PROXIES` automáticamente vía `config/trustedproxy.php`. Si el archivo no existe en tu proyecto, publícalo:
```bash
php artisan vendor:publish --tag=laravel-trustedproxies
```

Encabezados forwarded habituales que se deben confiar:
- `X-Forwarded-For` (IP real del cliente)
- `X-Forwarded-Proto` (HTTP/HTTPS)
- `X-Forwarded-Host`

Verifica la IP real con:
```bash
php artisan tinker
>>> request()->ip();  # Solo válido en request, no en tinker
```

O agrega un log temporal en un middleware para confirmar en producción.

## Retención de logs de auditoría

Las tablas `auth_events` y `audit_events` crecen con el tiempo. Se recomienda limpiarlas periódicamente.

Comando dedicado:
```bash
php artisan inmo:logs:prune
```

Defaults globales (configurables en `config/audit.php`):
- `auth_events`: 90 días
- `audit_events`: 180 días

Opciones útiles:
```bash
# Simulación (no borra)
php artisan inmo:logs:prune --dry-run

# Sobrescribir retenciones para esta ejecución
php artisan inmo:logs:prune --auth-days=120 --audit-days=365

# Borrar todo lo anterior a una fecha (ignora --*-days)
php artisan inmo:logs:prune --before=2026-01-01
```

Cron recomendado mensual:
```cron
15 3 1 * * /usr/bin/php /home/USUARIO_DOMINIO/public_html/artisan inmo:logs:prune >> /home/USUARIO_DOMINIO/logs/inmo-prune.log 2>&1
```

## Retención de backups (backup prune)

Comando dedicado:
```bash
php artisan inmo:backup:prune --dry-run
php artisan inmo:backup:prune --force --yes
```

Política recomendada:
- `keep_daily=14` (1 backup por día de los últimos 14 días)
- `keep_monthly=6` (1 backup por mes de los últimos 6 meses)
- `min_age_hours=24` (no borrar backups demasiado recientes)

Opciones útiles:
```bash
# Simulación
php artisan inmo:backup:prune --dry-run

# Ruta personalizada
php artisan inmo:backup:prune --path=storage/app/backups --dry-run

# Ejecutar borrado real
php artisan inmo:backup:prune --force --yes
```

---

## Comandos de referencia rapida

Web Hosting (sin Docker):
```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan inmo:backup
php artisan inmo:backup:prune --dry-run
php artisan inmo:backup:prune --force --yes
```

VPS (Docker):
```bash
docker compose up -d --build
docker compose exec app npm ci
docker compose exec app npm run build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan storage:link
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
docker compose exec app php artisan inmo:backup
docker compose exec app php artisan inmo:backup:prune --dry-run
docker compose exec app php artisan inmo:backup:prune --force --yes
```
