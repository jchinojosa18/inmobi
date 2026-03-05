# Restore de Backups

## 1) Formato real de backups en este repo

El comando actual [`inmo:backup`](../app/Console/Commands/InmoBackupCommand.php) guarda snapshots en:

- `storage/app/backups/<YYYYmmdd_HHMMSS>/`

Artefactos por snapshot:

- `manifest.json`
- `database.sql` (si DB MySQL) **o** `database.sqlite` (si DB SQLite)
- `documents.zip` (contenido de `storage/app/public/documents` en disk local configurado)

Notas:

- No se usa `db.sql.gz` en el backup actual (el dump sale como `database.sql`).
- `documents.zip` puede venir vacío si no existe carpeta `documents`.

## 2) Comando de restore

Comando:

```bash
php artisan inmo:backup:restore
```

Opciones:

- `--path=PATH`: ruta exacta del snapshot (directorio con `manifest.json`)
- `--latest`: usa el snapshot más reciente de `storage/app/backups`
- `--db-only`: restaura solo base de datos
- `--files-only`: restaura solo archivos (`documents.zip`)
- `--dry-run`: no aplica cambios; solo muestra plan
- `--force`: habilita cambios reales (sin esto el comando corre en modo seguro/dry-run)
- `--yes`: salta confirmación interactiva
- `--maintenance`: habilita `php artisan down` durante restore y `up` al final
- `--run-preflight`: ejecuta `inmo:preflight` al terminar (si existe)
- `--run-smoke --date=YYYY-MM-DD`: ejecuta `inmo:smoke` al terminar

## 3) Seguridad

- En `APP_ENV=production`, restore queda bloqueado si no se pasan **ambos** `--force` y `--yes`.
- Siempre muestra el destino de restore:
  - `DB_CONNECTION`
  - `DB_DATABASE`
  - ruta del backup elegido
- Antes de restaurar DB real, crea snapshot de seguridad en:
  - `storage/app/backups/pre-restore/<timestamp>/`
  - MySQL: `db-pre-restore.sql.gz`
  - SQLite: `db-pre-restore.sqlite`

## 4) Cómo listar backups disponibles

```bash
ls -la storage/app/backups
```

Nota:
- Backups antiguos pueden ser eliminados por `inmo:backup:prune` según política de retención (daily/monthly).
- No dependas de snapshots muy viejos como único plan de recuperación.

Ver contenido de un snapshot:

```bash
ls -la storage/app/backups/20260305_031000
cat storage/app/backups/20260305_031000/manifest.json
```

## 5) Ejemplos de uso

### Local (Sail)

Dry-run de snapshot más reciente:

```bash
./vendor/bin/sail artisan inmo:backup:restore --latest --dry-run
```

Restore completo real:

```bash
./vendor/bin/sail artisan inmo:backup:restore --latest --force --yes --maintenance --run-smoke --date=2026-03-10
```

Restore solo DB:

```bash
./vendor/bin/sail artisan inmo:backup:restore --path=storage/app/backups/20260305_031000 --db-only --force --yes
```

### Hostinger VPS (SSH)

```bash
php artisan inmo:backup:restore --latest --dry-run
php artisan inmo:backup:restore --latest --force --yes --maintenance --run-preflight
```

### Hostinger Web Hosting

Si no tienes cliente `mysql` en servidor:

- Opción recomendada: restaurar DB manualmente con phpMyAdmin importando `database.sql`.
- Para archivos, subir contenido de `documents.zip` descomprimido a `storage/app/public/documents`.
- Luego ejecutar:

```bash
php artisan config:clear
php artisan cache:clear
php artisan storage:link
```

## 6) Checklist post-restore

1. Ejecutar validaciones:
   - `php artisan inmo:preflight` (si existe)
   - `php artisan inmo:smoke --date=YYYY-MM-DD` (opcional)
2. Abrir aplicación y validar:
   - login/dashboard
   - detalle de contrato
   - descarga de recibo PDF
3. Revisar auditoría:
   - `/settings/audit`
   - eventos `backup.restore.started/completed/failed`
4. Confirmar storage:
   - `php artisan storage:link`
   - archivos en `storage/app/public/documents`
