<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestoreBackupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_force_runs_in_safe_dry_run_mode(): void
    {
        $backupPath = $this->makeBackupDirectory();

        $this->artisan('inmo:backup:restore', [
            '--path' => $backupPath,
            '--db-only' => true,
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('Modo seguro activo: sin --force se ejecuta únicamente dry-run.')
            ->expectsOutputToContain('DRY RUN: no se realizaron cambios.');
    }

    public function test_in_production_requires_force_and_yes(): void
    {
        $backupPath = $this->makeBackupDirectory();
        $originalEnv = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->artisan('inmo:backup:restore', [
                '--path' => $backupPath,
                '--db-only' => true,
            ])
                ->assertExitCode(1)
                ->expectsOutputToContain('Restore bloqueado en producción. Requiere --force y --yes.');
        } finally {
            $this->app['env'] = $originalEnv;
        }
    }

    public function test_it_fails_with_clear_message_when_backup_path_does_not_exist(): void
    {
        $missingPath = storage_path('app/backups/not-found-restore');

        $this->artisan('inmo:backup:restore', [
            '--path' => $missingPath,
            '--force' => true,
            '--yes' => true,
            '--db-only' => true,
        ])
            ->assertExitCode(1)
            ->expectsOutputToContain('Backup path no existe o no es directorio');
    }

    private function makeBackupDirectory(): string
    {
        $directory = storage_path('app/backups/test-restore-'.uniqid());
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($directory.'/manifest.json', json_encode([
            'timestamp' => now()->format('Ymd_His'),
            'database' => ['status' => 'ok'],
            'documents' => ['status' => 'ok'],
        ], JSON_PRETTY_PRINT));

        file_put_contents($directory.'/database.sqlite', 'sqlite-backup-content');

        return $directory;
    }
}
