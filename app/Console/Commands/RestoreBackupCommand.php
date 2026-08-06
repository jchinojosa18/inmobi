<?php

namespace App\Console\Commands;

use App\Support\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class RestoreBackupCommand extends Command
{
    private const LOCK_KEY = 'commands:inmo:backup:restore';

    private const LOCK_TTL_SECONDS = 7200;

    private const SNAPSHOT_TTL_SECONDS = 7200;

    protected $signature = 'inmo:backup:restore
        {--path= : Ruta exacta del backup (directorio con manifest.json)}
        {--latest : Restaura el backup más reciente en storage/app/backups}
        {--db-only : Restaura solo base de datos}
        {--files-only : Restaura solo archivos}
        {--dry-run : Solo muestra qué haría, sin cambios}
        {--force : Requerido para ejecutar cambios reales}
        {--yes : No pedir confirmación interactiva}
        {--maintenance : Ejecuta restore en maintenance mode}
        {--run-preflight : Ejecuta inmo:preflight al finalizar}
        {--run-smoke : Ejecuta inmo:smoke al finalizar}
        {--date= : Fecha para smoke (YYYY-MM-DD)}';

    protected $description = 'Restaura un backup de base de datos y/o documentos de forma segura.';

    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL_SECONDS);

        if (! $lock->get()) {
            Log::info('skipped (locked)', [
                'command' => $this->getName(),
                'lock_key' => self::LOCK_KEY,
            ]);
            $this->line('skipped (locked)');

            return self::SUCCESS;
        }

        $startedAt = microtime(true);
        $maintenanceEnabled = false;
        $backupPath = null;

        try {
            [$restoreDb, $restoreFiles] = $this->resolveRestoreScope();

            $force = (bool) $this->option('force');
            $explicitDryRun = (bool) $this->option('dry-run');
            $dryRun = $explicitDryRun || ! $force;
            $yes = (bool) $this->option('yes');
            $runPreflight = (bool) $this->option('run-preflight');
            $runSmoke = (bool) $this->option('run-smoke');
            $useMaintenance = (bool) $this->option('maintenance');

            if (app()->environment('production') && (! $force || ! $yes)) {
                $this->error('Restore bloqueado en producción. Requiere --force y --yes.');

                return self::FAILURE;
            }

            $backupPath = $this->resolveBackupPath(
                trim((string) $this->option('path')),
                (bool) $this->option('latest')
            );

            $artifacts = $this->resolveArtifacts($backupPath, $restoreDb, $restoreFiles);
            $dbConnection = (string) Config::get('database.default');
            $dbDatabase = (string) Config::get("database.connections.{$dbConnection}.database", '');

            $this->line("Backup path: {$backupPath}");
            $this->line("DB target: {$dbConnection} / {$dbDatabase}");
            $this->line('Restore scope: '.implode(', ', array_filter([
                $restoreDb ? 'db' : null,
                $restoreFiles ? 'files' : null,
            ])));

            if (! $force && ! $explicitDryRun) {
                $this->warn('Modo seguro activo: sin --force se ejecuta únicamente dry-run.');
            }

            $meta = [
                'backup_path' => $backupPath,
                'db_only' => (bool) $this->option('db-only'),
                'files_only' => (bool) $this->option('files-only'),
                'dry_run' => $dryRun,
                'maintenance' => $useMaintenance,
            ];

            $this->auditLogger->log(
                action: 'backup.restore.started',
                auditable: null,
                summary: "Restore iniciado desde {$backupPath}",
                meta: $meta,
            );

            if ($dryRun) {
                $this->line('DRY RUN: no se realizaron cambios.');
                $this->line($restoreDb
                    ? 'DB restore plan: '.($artifacts['db_file'] ?? 'sin archivo DB')
                    : 'DB restore plan: omitido');
                $this->line($restoreFiles
                    ? 'Files restore plan: '.($artifacts['documents_file'] ?? 'sin archivo documents')
                    : 'Files restore plan: omitido');

                return self::SUCCESS;
            }

            if (! $yes && $this->input->isInteractive()) {
                if (! $this->confirm('Esto sobrescribirá datos existentes. ¿Deseas continuar?', false)) {
                    $this->warn('Restore cancelado por el usuario.');

                    return self::FAILURE;
                }
            } elseif (! $yes && ! $this->input->isInteractive()) {
                $this->error('En modo no interactivo debes usar --yes para confirmar restore.');

                return self::FAILURE;
            }

            if ($useMaintenance) {
                Artisan::call('down');
                $maintenanceEnabled = true;
                $this->line('Maintenance mode activado.');
            }

            if ($restoreDb) {
                $snapshotPath = $this->createPreRestoreSnapshot($dbConnection, $dbDatabase);
                $this->line("Pre-restore snapshot: {$snapshotPath}");
                $this->restoreDatabase($artifacts['db_file'] ?? '', $dbConnection);
            }

            if ($restoreFiles) {
                $this->restoreDocuments($artifacts['documents_file'] ?? '');
            }

            if ($restoreDb) {
                Artisan::call('config:clear');
                Artisan::call('cache:clear');
                $this->line('Config/cache limpiados.');
            }

            if ($runPreflight) {
                $this->runOptionalCommand('inmo:preflight', []);
            }

            if ($runSmoke) {
                $smokeDate = $this->resolveSmokeDate(trim((string) $this->option('date')));
                $this->runOptionalCommand('inmo:smoke', ['--date' => $smokeDate]);
            }

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $this->auditLogger->log(
                action: 'backup.restore.completed',
                auditable: null,
                summary: "Restore completado desde {$backupPath}",
                meta: array_merge($meta, [
                    'duration_ms' => $durationMs,
                ]),
            );

            $this->info('Restore completado.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $this->auditLogger->log(
                action: 'backup.restore.failed',
                auditable: null,
                summary: 'Restore falló: '.$exception->getMessage(),
                meta: [
                    'backup_path' => $backupPath,
                    'duration_ms' => $durationMs,
                ],
            );

            $this->error('Restore falló: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            if ($maintenanceEnabled) {
                Artisan::call('up');
                $this->line('Maintenance mode desactivado.');
            }
            $lock->release();
        }
    }

    /**
     * @return array{0: bool, 1: bool}
     */
    private function resolveRestoreScope(): array
    {
        $dbOnly = (bool) $this->option('db-only');
        $filesOnly = (bool) $this->option('files-only');

        if ($dbOnly && $filesOnly) {
            throw new RuntimeException('No puedes usar --db-only y --files-only al mismo tiempo.');
        }

        return [
            ! $filesOnly,
            ! $dbOnly,
        ];
    }

    private function resolveBackupPath(string $pathOption, bool $latest): string
    {
        if ($pathOption !== '') {
            $resolved = str_starts_with($pathOption, '/')
                ? $pathOption
                : base_path($pathOption);

            if (! is_dir($resolved)) {
                throw new RuntimeException("Backup path no existe o no es directorio: {$resolved}");
            }

            return $resolved;
        }

        if (! $latest) {
            $latest = true;
        }

        if ($latest) {
            $baseDirectory = storage_path('app/backups');
            if (! is_dir($baseDirectory)) {
                throw new RuntimeException("No existe directorio de backups: {$baseDirectory}");
            }

            $snapshot = collect(scandir($baseDirectory) ?: [])
                ->filter(fn ($entry) => ! in_array($entry, ['.', '..'], true))
                ->map(fn ($entry) => $baseDirectory.DIRECTORY_SEPARATOR.$entry)
                ->filter(fn ($path) => is_dir($path))
                ->sortDesc()
                ->first();

            if (! is_string($snapshot) || $snapshot === '') {
                throw new RuntimeException('No se encontraron snapshots de backup para restaurar.');
            }

            return $snapshot;
        }

        throw new RuntimeException('Debes usar --path o --latest.');
    }

    /**
     * @return array{db_file:?string,documents_file:?string}
     */
    private function resolveArtifacts(string $backupPath, bool $restoreDb, bool $restoreFiles): array
    {
        $manifestPath = $backupPath.DIRECTORY_SEPARATOR.'manifest.json';
        if (! is_file($manifestPath)) {
            throw new RuntimeException("No se encontró manifest.json en backup: {$backupPath}");
        }

        $dbFileCandidates = [
            $backupPath.DIRECTORY_SEPARATOR.'database.sql',
            $backupPath.DIRECTORY_SEPARATOR.'database.sql.gz',
            $backupPath.DIRECTORY_SEPARATOR.'database.sqlite',
        ];

        $documentsFile = $backupPath.DIRECTORY_SEPARATOR.'documents.zip';

        $dbFile = collect($dbFileCandidates)
            ->first(fn ($candidate) => is_file($candidate));

        if ($restoreDb && ! is_string($dbFile)) {
            throw new RuntimeException('Falta archivo de base de datos en el backup (database.sql | database.sql.gz | database.sqlite).');
        }

        if ($restoreFiles && ! is_file($documentsFile)) {
            throw new RuntimeException('Falta archivo de documentos en el backup (documents.zip).');
        }

        return [
            'db_file' => is_string($dbFile) ? $dbFile : null,
            'documents_file' => is_file($documentsFile) ? $documentsFile : null,
        ];
    }

    private function createPreRestoreSnapshot(string $connection, string $database): string
    {
        $timestamp = now('America/Tijuana')->format('Ymd_His');
        $directory = storage_path('app/backups/pre-restore/'.$timestamp);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        if ($connection === 'sqlite') {
            $dbPath = (string) Config::get('database.connections.sqlite.database', '');
            if ($dbPath === '' || $dbPath === ':memory:') {
                throw new RuntimeException('No se puede crear pre-snapshot para sqlite en memoria.');
            }

            $source = str_starts_with($dbPath, '/')
                ? $dbPath
                : database_path($dbPath);
            $destination = $directory.DIRECTORY_SEPARATOR.'db-pre-restore.sqlite';

            if (! is_file($source) || ! copy($source, $destination)) {
                throw new RuntimeException('No se pudo generar pre-snapshot sqlite.');
            }

            return $destination;
        }

        if ($connection !== 'mysql') {
            throw new RuntimeException("Connection {$connection} no soportada para pre-snapshot.");
        }

        $mysql = (array) Config::get('database.connections.mysql', []);
        $host = (string) ($mysql['host'] ?? '127.0.0.1');
        $port = (string) ($mysql['port'] ?? '3306');
        $username = (string) ($mysql['username'] ?? '');
        $password = (string) ($mysql['password'] ?? '');

        if ($database === '' || $username === '') {
            throw new RuntimeException('Configuración MySQL incompleta para pre-snapshot.');
        }

        $command = [
            'mysqldump',
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            "--host={$host}",
            "--port={$port}",
            "--user={$username}",
        ];

        if ($password !== '') {
            $command[] = "--password={$password}";
        }
        $command[] = $database;

        $process = new Process($command);
        $process->setTimeout(self::SNAPSHOT_TTL_SECONDS);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('No se pudo generar pre-snapshot MySQL: '.trim($process->getErrorOutput()));
        }

        $destination = $directory.DIRECTORY_SEPARATOR.'db-pre-restore.sql.gz';
        $encoded = gzencode($process->getOutput(), 9);
        if ($encoded === false || file_put_contents($destination, $encoded) === false) {
            throw new RuntimeException('No se pudo guardar pre-snapshot comprimido.');
        }

        return $destination;
    }

    private function restoreDatabase(string $dbFile, string $connection): void
    {
        if ($dbFile === '' || ! is_file($dbFile)) {
            throw new RuntimeException('Archivo de base de datos inválido para restore.');
        }

        $this->line("Restaurando DB desde: {$dbFile}");

        if (str_ends_with($dbFile, '.sqlite')) {
            $this->restoreSqliteDatabase($dbFile);

            return;
        }

        $sqlFile = $dbFile;
        $temporaryFile = null;

        if (str_ends_with($dbFile, '.gz')) {
            $temporaryFile = $this->decompressGzipToTempFile($dbFile);
            $sqlFile = $temporaryFile;
        }

        try {
            if ($connection === 'mysql') {
                $this->restoreMysqlDatabase($sqlFile);
            } elseif ($connection === 'sqlite') {
                $this->restoreSqliteFromSql($sqlFile);
            } else {
                throw new RuntimeException("Connection {$connection} no soportada para restore SQL.");
            }

            if (! $this->dumpContainsSchema($sqlFile)) {
                Artisan::call('migrate', ['--force' => true]);
                $this->line('Schema no detectado en dump; se ejecutó migrate --force.');
            }
        } finally {
            if ($temporaryFile !== null && is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }
        }
    }

    private function restoreMysqlDatabase(string $sqlFile): void
    {
        if (! $this->binaryExists('mysql')) {
            throw new RuntimeException('No se encontró mysql client en PATH. Restore MySQL requiere cliente mysql en servidor/contenedor.');
        }

        $mysql = (array) Config::get('database.connections.mysql', []);
        $host = (string) ($mysql['host'] ?? '127.0.0.1');
        $port = (string) ($mysql['port'] ?? '3306');
        $database = (string) ($mysql['database'] ?? '');
        $username = (string) ($mysql['username'] ?? '');
        $password = (string) ($mysql['password'] ?? '');

        if ($database === '' || $username === '') {
            throw new RuntimeException('Configuración MySQL incompleta para restore.');
        }

        $command = 'mysql'
            .' --host='.escapeshellarg($host)
            .' --port='.escapeshellarg($port)
            .' --user='.escapeshellarg($username);

        if ($password !== '') {
            $command .= ' --password='.escapeshellarg($password);
        }

        $command .= ' '.escapeshellarg($database)
            .' < '.escapeshellarg($sqlFile);

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(self::LOCK_TTL_SECONDS);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Restore MySQL falló: '.trim($process->getErrorOutput()));
        }
    }

    private function restoreSqliteDatabase(string $sqliteBackupPath): void
    {
        $dbPath = (string) Config::get('database.connections.sqlite.database', '');
        if ($dbPath === '' || $dbPath === ':memory:') {
            throw new RuntimeException('SQLite in-memory no soporta restore de archivo.');
        }

        $target = str_starts_with($dbPath, '/')
            ? $dbPath
            : database_path($dbPath);

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }

        if (! copy($sqliteBackupPath, $target)) {
            throw new RuntimeException('No se pudo restaurar archivo sqlite.');
        }
    }

    private function restoreSqliteFromSql(string $sqlFile): void
    {
        $dbPath = (string) Config::get('database.connections.sqlite.database', '');
        if ($dbPath === '' || $dbPath === ':memory:') {
            throw new RuntimeException('SQLite in-memory no soporta restore SQL.');
        }

        $target = str_starts_with($dbPath, '/')
            ? $dbPath
            : database_path($dbPath);

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }

        $pdo = new \PDO('sqlite:'.$target);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            throw new RuntimeException('No se pudo leer el dump SQL.');
        }

        $pdo->exec($sql);
    }

    private function restoreDocuments(string $documentsZipPath): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('La extensión ZIP no está disponible para restore de documentos.');
        }

        if (! is_file($documentsZipPath)) {
            throw new RuntimeException('Archivo documents.zip no encontrado para restore.');
        }

        $disk = (string) config('filesystems.documents_disk', 'public');
        $diskConfig = (array) config("filesystems.disks.{$disk}", []);
        $driver = (string) ($diskConfig['driver'] ?? '');

        if ($driver !== 'local') {
            throw new RuntimeException("Restore de documentos soporta solo disks locales. Actual: {$driver}");
        }

        $root = (string) ($diskConfig['root'] ?? '');
        if ($root === '') {
            throw new RuntimeException('No se encontró root del disk de documentos.');
        }

        $targetDocumentsPath = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'documents';
        if (! is_dir($targetDocumentsPath)) {
            mkdir($targetDocumentsPath, 0775, true);
        }

        $tempExtractPath = storage_path('app/tmp/restore-documents-'.uniqid());
        mkdir($tempExtractPath, 0775, true);

        try {
            $zip = new ZipArchive;
            if ($zip->open($documentsZipPath) !== true) {
                throw new RuntimeException('No se pudo abrir documents.zip para restore.');
            }

            if (! $zip->extractTo($tempExtractPath)) {
                $zip->close();
                throw new RuntimeException('No se pudo extraer documents.zip.');
            }
            $zip->close();

            $this->copyDirectoryContents($tempExtractPath, $targetDocumentsPath);
        } finally {
            File::deleteDirectory($tempExtractPath);
        }
    }

    private function copyDirectoryContents(string $source, string $destination): void
    {
        $entries = File::allFiles($source);
        foreach ($entries as $entry) {
            $relative = ltrim(substr($entry->getPathname(), strlen($source)), DIRECTORY_SEPARATOR);
            $target = $destination.DIRECTORY_SEPARATOR.$relative;
            $targetDir = dirname($target);
            if (! is_dir($targetDir)) {
                mkdir($targetDir, 0775, true);
            }

            copy($entry->getPathname(), $target);
            @chmod($target, 0664);
        }
    }

    private function dumpContainsSchema(string $sqlFile): bool
    {
        $handle = fopen($sqlFile, 'rb');
        if ($handle === false) {
            return true;
        }

        $chunk = fread($handle, 1024 * 1024);
        fclose($handle);

        if ($chunk === false || $chunk === '') {
            return false;
        }

        return preg_match('/\b(CREATE|DROP)\s+TABLE\b/i', $chunk) === 1;
    }

    private function decompressGzipToTempFile(string $gzFile): string
    {
        $tmpDirectory = storage_path('app/tmp');
        if (! is_dir($tmpDirectory)) {
            mkdir($tmpDirectory, 0775, true);
        }

        $handle = gzopen($gzFile, 'rb');
        if ($handle === false) {
            throw new RuntimeException("No se pudo abrir archivo gzip: {$gzFile}");
        }

        $tempFile = tempnam($tmpDirectory, 'restore-sql-');
        if ($tempFile === false) {
            gzclose($handle);
            throw new RuntimeException('No se pudo crear archivo temporal para SQL descomprimido.');
        }

        $target = fopen($tempFile, 'wb');
        if ($target === false) {
            gzclose($handle);
            @unlink($tempFile);
            throw new RuntimeException('No se pudo abrir archivo temporal para escribir SQL.');
        }

        while (! gzeof($handle)) {
            $buffer = gzread($handle, 8192);
            if ($buffer === false) {
                fclose($target);
                gzclose($handle);
                @unlink($tempFile);
                throw new RuntimeException("No se pudo descomprimir {$gzFile}");
            }
            fwrite($target, $buffer);
        }

        fclose($target);
        gzclose($handle);

        return $tempFile;
    }

    private function runOptionalCommand(string $command, array $options): void
    {
        try {
            $exitCode = Artisan::call($command, $options);
        } catch (CommandNotFoundException) {
            $this->warn("Comando opcional no disponible: {$command}");

            return;
        }

        $output = trim(Artisan::output());
        $this->line("Post-command {$command}: ejecutado");
        if ($output !== '') {
            $this->line($output);
        }
        if ($exitCode !== 0) {
            throw new RuntimeException("El comando {$command} falló.");
        }
    }

    private function binaryExists(string $binary): bool
    {
        $process = new Process(['which', $binary]);
        $process->run();

        return $process->isSuccessful() && trim($process->getOutput()) !== '';
    }

    private function resolveSmokeDate(string $dateOption): string
    {
        if ($dateOption === '') {
            return CarbonImmutable::now('America/Tijuana')->format('Y-m-d');
        }

        $validator = Validator::make(
            ['date' => $dateOption],
            ['date' => ['required', 'date_format:Y-m-d']]
        );

        if ($validator->fails()) {
            throw new RuntimeException('La fecha de --date para smoke debe usar formato YYYY-MM-DD.');
        }

        return $dateOption;
    }
}
