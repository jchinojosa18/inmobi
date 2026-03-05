<?php

namespace App\Console\Commands;

use App\Support\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PruneBackupsCommand extends Command
{
    private const LOCK_KEY = 'commands:inmo:backup:prune';

    private const LOCK_TTL_SECONDS = 1800;

    private const BACKUP_NAME_PATTERN = '/^\d{8}_\d{6}$/';

    protected $signature = 'inmo:backup:prune
        {--path= : Ruta base de backups (default: storage/app/backups)}
        {--keep-daily=14 : Días a conservar (1 backup por día)}
        {--keep-monthly=6 : Meses a conservar (1 backup por mes)}
        {--dry-run : Muestra plan sin borrar}
        {--force : Requerido para borrar}
        {--yes : No pedir confirmación interactiva}
        {--min-age-hours=24 : No borrar backups más nuevos que este umbral}';

    protected $description = 'Aplica retención diaria/mensual sobre snapshots de backups.';

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

        try {
            $force = (bool) $this->option('force');
            $explicitDryRun = (bool) $this->option('dry-run');
            $dryRun = $explicitDryRun || ! $force;
            $yes = (bool) $this->option('yes');

            if (app()->environment('production') && ! $dryRun && (! $force || ! $yes)) {
                $this->error('Prune de backups bloqueado en producción. Requiere --force y --yes.');

                return self::FAILURE;
            }

            $basePath = $this->resolveBasePath((string) $this->option('path'));
            $keepDaily = max((int) $this->option('keep-daily'), 0);
            $keepMonthly = max((int) $this->option('keep-monthly'), 0);
            $minAgeHours = max((int) $this->option('min-age-hours'), 0);

            $snapshots = $this->discoverSnapshots($basePath);
            if ($snapshots->isEmpty()) {
                $this->warn("No se encontraron backups válidos en: {$basePath}");

                return self::SUCCESS;
            }

            $latest = $snapshots->first();
            if ($latest === null) {
                $this->warn("No se encontraron backups válidos en: {$basePath}");

                return self::SUCCESS;
            }

            $plan = $this->buildPlan($snapshots, $keepDaily, $keepMonthly, $minAgeHours);

            $this->line("Path: {$basePath}");
            $this->line("Backups detectados: {$snapshots->count()}");
            $this->line("Backups retenidos: {$plan['keep']->count()}");
            $this->line("Backups a eliminar: {$plan['delete']->count()}");
            $this->line('Backup más reciente (siempre retenido): '.$latest['path']);
            $this->line('Espacio estimado liberado: '.$this->formatBytes($plan['delete_bytes']));

            if ($plan['delete']->isNotEmpty()) {
                $this->line('Candidatos a eliminar:');
                foreach ($plan['delete'] as $entry) {
                    $this->line(' - '.$entry['path']);
                }
            }

            $meta = [
                'path' => $basePath,
                'keep_daily' => $keepDaily,
                'keep_monthly' => $keepMonthly,
                'min_age_hours' => $minAgeHours,
                'detected' => $snapshots->count(),
                'retained' => $plan['keep']->count(),
                'to_delete' => $plan['delete']->count(),
                'estimated_released_bytes' => $plan['delete_bytes'],
                'dry_run' => $dryRun,
            ];

            if ($dryRun) {
                if (! $explicitDryRun) {
                    $this->warn('Modo seguro activo: sin --force se ejecuta dry-run.');
                }
                $this->auditLogger->log(
                    action: 'backup.prune.dry_run',
                    auditable: null,
                    summary: "Backup prune dry-run en {$basePath}",
                    meta: $meta,
                );

                return self::SUCCESS;
            }

            if (! $yes && $this->input->isInteractive()) {
                if (! $this->confirm('Se eliminarán backups listados. ¿Deseas continuar?', false)) {
                    $this->warn('Prune cancelado por el usuario.');

                    return self::FAILURE;
                }
            } elseif (! $yes && ! $this->input->isInteractive()) {
                $this->error('En modo no interactivo usa --yes para confirmar.');

                return self::FAILURE;
            }

            $deleted = 0;
            foreach ($plan['delete'] as $entry) {
                $path = (string) $entry['path'];
                if (! $this->deletePath($path)) {
                    throw new RuntimeException("No se pudo eliminar backup: {$path}");
                }
                $deleted++;
            }

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            $this->auditLogger->log(
                action: 'backup.prune.completed',
                auditable: null,
                summary: "Backup prune completado en {$basePath}",
                meta: array_merge($meta, [
                    'deleted' => $deleted,
                    'duration_ms' => $durationMs,
                ]),
            );

            $this->info("Backups eliminados: {$deleted}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            $this->auditLogger->log(
                action: 'backup.prune.failed',
                auditable: null,
                summary: 'Backup prune falló: '.$exception->getMessage(),
                meta: [
                    'duration_ms' => $durationMs,
                ],
            );

            $this->error('Prune falló: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }

    private function resolveBasePath(string $pathOption): string
    {
        $path = trim($pathOption);
        if ($path === '') {
            $path = storage_path('app/backups');
        } elseif (! str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        if (! is_dir($path)) {
            throw new RuntimeException("No existe directorio de backups: {$path}");
        }

        return $path;
    }

    /**
     * @return Collection<int, array{name:string,path:string,datetime:CarbonImmutable,size:int,is_dir:bool}>
     */
    private function discoverSnapshots(string $basePath): Collection
    {
        return collect(scandir($basePath) ?: [])
            ->filter(fn ($entry) => ! in_array($entry, ['.', '..'], true))
            ->map(function (string $entry) use ($basePath): ?array {
                if (! preg_match(self::BACKUP_NAME_PATTERN, $entry)) {
                    return null;
                }

                $absolutePath = $basePath.DIRECTORY_SEPARATOR.$entry;
                if (! is_dir($absolutePath) && ! is_file($absolutePath)) {
                    return null;
                }

                $dt = CarbonImmutable::createFromFormat('Ymd_His', $entry, 'America/Tijuana');
                if ($dt === false) {
                    $dt = CarbonImmutable::createFromTimestamp((int) filemtime($absolutePath), 'America/Tijuana');
                }

                return [
                    'name' => $entry,
                    'path' => $absolutePath,
                    'datetime' => $dt,
                    'size' => $this->calculatePathSize($absolutePath),
                    'is_dir' => is_dir($absolutePath),
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $entry): int => $entry['datetime']->getTimestamp())
            ->values();
    }

    /**
     * @param  Collection<int, array{name:string,path:string,datetime:CarbonImmutable,size:int,is_dir:bool}>  $snapshots
     * @return array{
     *   keep: Collection<int, array{name:string,path:string,datetime:CarbonImmutable,size:int,is_dir:bool}>,
     *   delete: Collection<int, array{name:string,path:string,datetime:CarbonImmutable,size:int,is_dir:bool}>,
     *   delete_bytes:int
     * }
     */
    private function buildPlan(Collection $snapshots, int $keepDaily, int $keepMonthly, int $minAgeHours): array
    {
        $keepKeys = collect();
        $indexByPath = $snapshots
            ->mapWithKeys(fn (array $entry, int $index): array => [$entry['path'] => $index]);

        $latest = $snapshots->first();
        if ($latest !== null) {
            $keepKeys->push((int) $indexByPath[$latest['path']]);
        }

        // Keep one backup per day for keep_daily distinct days.
        $daysSelected = [];
        if ($keepDaily > 0) {
            foreach ($snapshots as $entry) {
                $day = $entry['datetime']->format('Y-m-d');
                if (in_array($day, $daysSelected, true)) {
                    continue;
                }
                $keepKeys->push((int) $indexByPath[$entry['path']]);
                $daysSelected[] = $day;

                if (count($daysSelected) >= $keepDaily) {
                    break;
                }
            }
        }

        // Keep one backup per month for keep_monthly recent months.
        if ($keepMonthly > 0) {
            $currentMonth = CarbonImmutable::now('America/Tijuana')->startOfMonth();
            $monthsToKeep = collect(range(0, $keepMonthly - 1))
                ->map(fn (int $offset): string => $currentMonth->subMonthsNoOverflow($offset)->format('Y-m'))
                ->all();

            $monthSelected = [];
            foreach ($snapshots as $entry) {
                $month = $entry['datetime']->format('Y-m');
                if (! in_array($month, $monthsToKeep, true)) {
                    continue;
                }
                if (in_array($month, $monthSelected, true)) {
                    continue;
                }

                $index = (int) $indexByPath[$entry['path']];
                if (! $keepKeys->contains($index)) {
                    $keepKeys->push($index);
                }
                $monthSelected[] = $month;
            }
        }

        $keepKeys = $keepKeys->unique()->values();

        $minAgeCutoff = CarbonImmutable::now('America/Tijuana')->subHours($minAgeHours);

        $delete = $snapshots
            ->reject(fn (array $entry, int $index): bool => $keepKeys->contains($index))
            ->filter(fn (array $entry): bool => $entry['datetime']->lte($minAgeCutoff))
            ->values();

        // Safety net: newest backup must stay.
        if ($latest !== null) {
            $delete = $delete->reject(fn (array $entry): bool => $entry['path'] === $latest['path'])->values();
        }

        $keep = $snapshots
            ->reject(fn (array $entry) => $delete->contains(fn (array $candidate): bool => $candidate['path'] === $entry['path']))
            ->values();

        return [
            'keep' => $keep,
            'delete' => $delete,
            'delete_bytes' => (int) $delete->sum('size'),
        ];
    }

    private function calculatePathSize(string $path): int
    {
        if (is_file($path)) {
            return (int) filesize($path);
        }

        if (! is_dir($path)) {
            return 0;
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $item */
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $size += $item->getSize();
            }
        }

        return $size;
    }

    private function deletePath(string $path): bool
    {
        if (is_dir($path)) {
            return File::deleteDirectory($path);
        }

        if (is_file($path)) {
            return File::delete($path);
        }

        return true;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = max($bytes, 0);
        $power = $size > 0 ? (int) floor(log($size, 1024)) : 0;
        $power = min($power, count($units) - 1);
        $value = $size / (1024 ** $power);

        return number_format($value, 2).' '.$units[$power];
    }
}
