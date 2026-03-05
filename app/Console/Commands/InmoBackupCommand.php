<?php

namespace App\Console\Commands;

use App\Support\SystemHeartbeatService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;
use ZipArchive;

class InmoBackupCommand extends Command
{
    private const LOCK_KEY = 'commands:inmo:backup';

    private const LOCK_TTL_SECONDS = 3600;

    protected $signature = 'inmo:backup
        {--keep= : Cantidad de snapshots de backup a conservar (opcional)}
        {--skip-db : Omitir backup de base de datos}
        {--skip-documents : Omitir backup de documentos}';

    protected $description = 'Genera backup de base de datos + zip de documentos con rotación simple';

    public function handle(SystemHeartbeatService $heartbeatService): int
    {
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL_SECONDS);

        if (! $lock->get()) {
            $this->line('skipped (locked)');
            $heartbeatService->touch('backup', 'locked', [
                'command' => $this->getName(),
            ]);

            return self::SUCCESS;
        }

        try {
            $keepOption = trim((string) $this->option('keep'));
            $keep = $keepOption === '' ? null : max((int) $keepOption, 1);
            $skipDb = (bool) $this->option('skip-db');
            $skipDocuments = (bool) $this->option('skip-documents');

            $timestamp = now('America/Tijuana')->format('Ymd_His');
            $baseDirectory = storage_path('app/backups');
            $runDirectory = $baseDirectory.DIRECTORY_SEPARATOR.$timestamp;

            if (! is_dir($runDirectory)) {
                mkdir($runDirectory, 0775, true);
            }

            $result = [
                'timestamp' => $timestamp,
                'timezone' => 'America/Tijuana',
                'started_at' => now()->toIso8601String(),
                'database' => $skipDb
                    ? ['status' => 'skipped', 'message' => 'Omitido por --skip-db']
                    : $this->backupDatabase($runDirectory),
                'documents' => $skipDocuments
                    ? ['status' => 'skipped', 'message' => 'Omitido por --skip-documents']
                    : $this->backupDocuments($runDirectory),
            ];

            $result['finished_at'] = now()->toIso8601String();

            file_put_contents(
                $runDirectory.DIRECTORY_SEPARATOR.'manifest.json',
                json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            if ($keep !== null) {
                $this->rotateBackups($baseDirectory, $keep);
            }

            $dbStatus = (string) data_get($result, 'database.status', 'failed');
            $documentsStatus = (string) data_get($result, 'documents.status', 'failed');
            $overallStatus = $dbStatus === 'ok' && $documentsStatus === 'ok'
                ? 'ok'
                : 'warning';

            $heartbeatService->touch('backup', $overallStatus, [
                'run_directory' => $runDirectory,
                'database' => $result['database'],
                'documents' => $result['documents'],
            ]);

            $this->info("Backup ejecutado en: {$runDirectory}");
            $this->line('Database: '.data_get($result, 'database.status').' - '.data_get($result, 'database.message'));
            $this->line('Documents: '.data_get($result, 'documents.status').' - '.data_get($result, 'documents.message'));
            if ($keep !== null) {
                $this->line("Rotación aplicada: conservar {$keep} snapshots");
            } else {
                $this->line('Rotación simple omitida (usa inmo:backup:prune para política diaria/mensual).');
            }

            return $overallStatus === 'ok' ? self::SUCCESS : self::FAILURE;
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{status:string, message:string, file?:string}
     */
    private function backupDatabase(string $runDirectory): array
    {
        $defaultConnection = (string) Config::get('database.default');
        $connection = (array) Config::get("database.connections.{$defaultConnection}", []);
        $driver = (string) ($connection['driver'] ?? '');

        if ($driver === 'sqlite') {
            return $this->backupSqliteDatabase($connection, $runDirectory);
        }

        if ($driver === 'mysql') {
            return $this->backupMysqlDatabase($connection, $runDirectory);
        }

        return [
            'status' => 'failed',
            'message' => "Driver de DB no soportado para backup automático: {$driver}",
        ];
    }

    /**
     * @param  array<string, mixed>  $connection
     * @return array{status:string, message:string, file?:string}
     */
    private function backupSqliteDatabase(array $connection, string $runDirectory): array
    {
        $database = (string) ($connection['database'] ?? '');

        if ($database === '' || $database === ':memory:') {
            return [
                'status' => 'failed',
                'message' => 'SQLite en memoria no permite backup de archivo.',
            ];
        }

        $sourcePath = Str::startsWith($database, ['/'])
            ? $database
            : database_path($database);

        if (! is_file($sourcePath)) {
            return [
                'status' => 'failed',
                'message' => "Archivo SQLite no encontrado: {$sourcePath}",
            ];
        }

        $destination = $runDirectory.DIRECTORY_SEPARATOR.'database.sqlite';

        if (! copy($sourcePath, $destination)) {
            return [
                'status' => 'failed',
                'message' => 'No se pudo copiar el archivo SQLite.',
            ];
        }

        return [
            'status' => 'ok',
            'message' => 'Backup SQLite completado.',
            'file' => $destination,
        ];
    }

    /**
     * @param  array<string, mixed>  $connection
     * @return array{status:string, message:string, file?:string}
     */
    private function backupMysqlDatabase(array $connection, string $runDirectory): array
    {
        $host = (string) ($connection['host'] ?? '127.0.0.1');
        $port = (string) ($connection['port'] ?? '3306');
        $database = (string) ($connection['database'] ?? '');
        $username = (string) ($connection['username'] ?? '');
        $password = (string) ($connection['password'] ?? '');

        if ($database === '' || $username === '') {
            return [
                'status' => 'failed',
                'message' => 'Configuración de MySQL incompleta para backup.',
            ];
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
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput());

            return [
                'status' => 'failed',
                'message' => $error !== '' ? $error : 'mysqldump falló sin detalle.',
            ];
        }

        $destination = $runDirectory.DIRECTORY_SEPARATOR.'database.sql';
        file_put_contents($destination, $process->getOutput());

        return [
            'status' => 'ok',
            'message' => 'Backup MySQL completado.',
            'file' => $destination,
        ];
    }

    /**
     * @return array{status:string, message:string, file?:string}
     */
    private function backupDocuments(string $runDirectory): array
    {
        if (! class_exists(ZipArchive::class)) {
            return [
                'status' => 'failed',
                'message' => 'La extensión ZIP no está disponible en PHP.',
            ];
        }

        $disk = (string) config('filesystems.documents_disk', 'public');
        $diskConfig = (array) config("filesystems.disks.{$disk}", []);
        $driver = (string) ($diskConfig['driver'] ?? '');

        if ($driver !== 'local') {
            return [
                'status' => 'failed',
                'message' => "El disk {$disk} usa driver {$driver}; backup zip local no soportado.",
            ];
        }

        $root = (string) ($diskConfig['root'] ?? '');
        if ($root === '') {
            return [
                'status' => 'failed',
                'message' => 'No se encontró root del disk de documentos.',
            ];
        }

        $documentsPath = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'documents';
        $zipFile = $runDirectory.DIRECTORY_SEPARATOR.'documents.zip';

        $zip = new ZipArchive;
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return [
                'status' => 'failed',
                'message' => 'No se pudo crear el archivo ZIP de documentos.',
            ];
        }

        if (is_dir($documentsPath)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($documentsPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $absolutePath = $item->getPathname();
                $relativePath = ltrim(substr($absolutePath, strlen($documentsPath)), DIRECTORY_SEPARATOR);

                if ($relativePath === '') {
                    continue;
                }

                if ($item->isDir()) {
                    $zip->addEmptyDir($relativePath);
                } else {
                    $zip->addFile($absolutePath, $relativePath);
                }
            }
        }

        $zip->close();

        return [
            'status' => 'ok',
            'message' => is_dir($documentsPath)
                ? 'ZIP de documentos generado.'
                : 'No existe carpeta documents; ZIP generado vacío.',
            'file' => $zipFile,
        ];
    }

    private function rotateBackups(string $baseDirectory, int $keep): void
    {
        if (! is_dir($baseDirectory)) {
            return;
        }

        $directories = collect(scandir($baseDirectory) ?: [])
            ->filter(fn ($entry) => ! in_array($entry, ['.', '..'], true))
            ->map(fn ($entry) => $baseDirectory.DIRECTORY_SEPARATOR.$entry)
            ->filter(fn ($path) => is_dir($path))
            ->sortDesc()
            ->values();

        $directories
            ->slice($keep)
            ->each(fn (string $directory) => $this->deleteDirectoryRecursively($directory));
    }

    private function deleteDirectoryRecursively(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = array_diff(scandir($directory) ?: [], ['.', '..']);

        foreach ($items as $item) {
            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $this->deleteDirectoryRecursively($path);

                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
