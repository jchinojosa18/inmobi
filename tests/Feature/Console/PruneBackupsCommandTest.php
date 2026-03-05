<?php

namespace Tests\Feature\Console;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneBackupsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_delete_backups(): void
    {
        CarbonImmutable::setTestNow('2026-03-20 12:00:00');
        $basePath = $this->makeBasePath('dry-run');
        $this->createBackups($basePath, [
            '20260320_010000',
            '20260320_000000',
            '20260319_010000',
            '20260227_010000',
        ]);

        $this->artisan('inmo:backup:prune', [
            '--path' => $basePath,
            '--keep-daily' => 1,
            '--keep-monthly' => 1,
            '--dry-run' => true,
            '--min-age-hours' => 0,
        ])->assertExitCode(0)
            ->expectsOutputToContain('Backups detectados: 4')
            ->expectsOutputToContain('Backups a eliminar: 3');

        $this->assertSame(4, $this->countBackups($basePath));
        CarbonImmutable::setTestNow();
    }

    public function test_force_applies_daily_and_monthly_retention_and_keeps_latest(): void
    {
        CarbonImmutable::setTestNow('2026-03-20 12:00:00');
        $basePath = $this->makeBasePath('force-retention');
        $this->createBackups($basePath, [
            '20260320_010000',
            '20260320_000000',
            '20260319_010000',
            '20260318_010000',
            '20260227_010000',
            '20260201_010000',
            '20260115_010000',
            '20260101_010000',
            '20251215_010000',
        ]);

        $this->artisan('inmo:backup:prune', [
            '--path' => $basePath,
            '--keep-daily' => 2,
            '--keep-monthly' => 2,
            '--min-age-hours' => 0,
            '--force' => true,
            '--yes' => true,
        ])->assertExitCode(0)
            ->expectsOutputToContain('Backups detectados: 9')
            ->expectsOutputToContain('Backups a eliminar: 6')
            ->expectsOutputToContain('Backups eliminados: 6');

        $remaining = $this->listBackupNames($basePath);
        sort($remaining);

        $this->assertSame([
            '20260227_010000',
            '20260319_010000',
            '20260320_010000',
        ], $remaining);

        // Idempotency: second run should not delete more.
        $this->artisan('inmo:backup:prune', [
            '--path' => $basePath,
            '--keep-daily' => 2,
            '--keep-monthly' => 2,
            '--min-age-hours' => 0,
            '--force' => true,
            '--yes' => true,
        ])->assertExitCode(0)
            ->expectsOutputToContain('Backups a eliminar: 0')
            ->expectsOutputToContain('Backups eliminados: 0');

        CarbonImmutable::setTestNow();
    }

    public function test_min_age_hours_prevents_deleting_recent_backups(): void
    {
        CarbonImmutable::setTestNow('2026-03-20 10:00:00');
        $basePath = $this->makeBasePath('min-age');
        $this->createBackups($basePath, [
            '20260320_090000',
            '20260320_070000',
            '20260319_010000',
        ]);

        $this->artisan('inmo:backup:prune', [
            '--path' => $basePath,
            '--keep-daily' => 1,
            '--keep-monthly' => 0,
            '--min-age-hours' => 24,
            '--force' => true,
            '--yes' => true,
        ])->assertExitCode(0)
            ->expectsOutputToContain('Backups a eliminar: 1')
            ->expectsOutputToContain('Backups eliminados: 1');

        $remaining = $this->listBackupNames($basePath);
        sort($remaining);

        $this->assertSame([
            '20260320_070000',
            '20260320_090000',
        ], $remaining);

        CarbonImmutable::setTestNow();
    }

    public function test_in_production_requires_force_and_yes_to_delete(): void
    {
        $basePath = $this->makeBasePath('production-guard');
        $this->createBackups($basePath, [
            '20260320_010000',
        ]);

        $originalEnv = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->artisan('inmo:backup:prune', [
                '--path' => $basePath,
                '--force' => true,
            ])->assertExitCode(1)
                ->expectsOutputToContain('Prune de backups bloqueado en producción. Requiere --force y --yes.');
        } finally {
            $this->app['env'] = $originalEnv;
        }
    }

    public function test_it_fails_with_clear_message_when_path_is_missing(): void
    {
        $missing = storage_path('app/non-existent-backups-path');

        $this->artisan('inmo:backup:prune', [
            '--path' => $missing,
            '--dry-run' => true,
        ])->assertExitCode(1)
            ->expectsOutputToContain('No existe directorio de backups:');
    }

    private function makeBasePath(string $suffix): string
    {
        $path = storage_path('framework/testing-backup-prune-'.$suffix.'-'.uniqid());
        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }

        return $path;
    }

    /**
     * @param  list<string>  $names
     */
    private function createBackups(string $basePath, array $names): void
    {
        foreach ($names as $name) {
            $directory = $basePath.DIRECTORY_SEPARATOR.$name;
            if (! is_dir($directory)) {
                mkdir($directory, 0775, true);
            }
            file_put_contents($directory.DIRECTORY_SEPARATOR.'manifest.json', '{"ok":true}');
        }
    }

    private function countBackups(string $basePath): int
    {
        return count($this->listBackupNames($basePath));
    }

    /**
     * @return list<string>
     */
    private function listBackupNames(string $basePath): array
    {
        return collect(scandir($basePath) ?: [])
            ->filter(fn ($entry) => ! in_array($entry, ['.', '..'], true))
            ->filter(fn ($entry) => is_dir($basePath.DIRECTORY_SEPARATOR.$entry))
            ->values()
            ->all();
    }
}
