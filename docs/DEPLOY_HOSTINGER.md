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
1. Preparar build local (frontend):
```bash
npm ci
npm run build
```

2. Subir proyecto al servidor (Git, SFTP o File Manager), incluyendo:
- Codigo fuente Laravel.
- Carpeta `vendor/` solo si no correras Composer en servidor.
- Carpeta `public/build` generada localmente.

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

6. Inicializar app y publicar cache:
```bash
php artisan key:generate
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

7. Ejecutar migraciones:
```bash
php artisan migrate --force
```

8. Crear enlace de almacenamiento publico:
```bash
php artisan storage:link
```

9. Ajustar permisos de escritura en:
- `storage/`
- `bootstrap/cache/`

Permisos comunes:
- Directorios: `775`
- Archivos: `664`

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

5. Ejecutar tareas de bootstrap dentro del contenedor de app:
```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan storage:link
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

6. Configurar reverse proxy (Nginx):
- Escuchar en `80/443`.
- Apuntar al servicio PHP/Laravel correspondiente.
- Servir archivos estaticos y enrutar dinamicos a la app.
- Configurar headers de proxy (`X-Forwarded-*`) y limites de upload.

7. SSL (notas generales):
- Usar certificados validos (ejemplo: Let's Encrypt).
- Configurar renovacion automatica del certificado.
- Forzar redireccion HTTP -> HTTPS.

8. Scheduler y queue en contenedores dedicados o procesos supervisados:
- Scheduler: `php artisan schedule:work` o cron `schedule:run`.
- Queue worker: `php artisan queue:work redis ...`

9. Backups:
- Base de datos: dumps periodicos + retencion definida.
- Archivos: `storage/` y/o bucket de documentos.
- Infraestructura: respaldo de volumenes y versionado de configuraciones.

### Checklist de salida (VPS Docker)
- [ ] Contenedores `up` y saludables.
- [ ] Reverse proxy funcionando en dominio productivo.
- [ ] HTTPS activo con renovacion prevista.
- [ ] Migraciones aplicadas.
- [ ] Scheduler y queue operando.
- [ ] Politica de backups documentada y probada (restore test).
- [ ] Monitoreo/logs minimos activos (app + proxy + DB).

---

## Comandos de referencia rapida

Web Hosting (sin Docker):
```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
```

VPS (Docker):
```bash
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan storage:link
```
