<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_command_creates_snapshot_with_database_and_documents_zip(): void
    {
        if (! class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive extension no disponible en el entorno de pruebas.');
        }

        $sqlitePath = storage_path('framework/testing-backup.sqlite');
        if (! is_dir(dirname($sqlitePath))) {
            mkdir(dirname($sqlitePath), 0775, true);
        }
        file_put_contents($sqlitePath, 'sqlite-backup');

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', $sqlitePath);

        $documentsDir = storage_path('app/public/documents/testing');
        if (! is_dir($documentsDir)) {
            mkdir($documentsDir, 0775, true);
        }
        file_put_contents($documentsDir.'/evidence.txt', 'demo-evidence');

        $this->deleteDirectory(storage_path('app/backups'));

        $this->artisan('inmo:backup --keep=2')
            ->assertExitCode(0);

        $snapshots = collect(scandir(storage_path('app/backups')) ?: [])
            ->filter(fn ($entry) => ! in_array($entry, ['.', '..'], true))
            ->values();

        $this->assertCount(1, $snapshots);

        $snapshotPath = storage_path('app/backups/'.$snapshots->first());

        $this->assertFileExists($snapshotPath.'/manifest.json');
        $this->assertFileExists($snapshotPath.'/database.sqlite');
        $this->assertFileExists($snapshotPath.'/documents.zip');
    }

    public function test_backup_rotation_keeps_only_configured_snapshots(): void
    {
        if (! class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive extension no disponible en el entorno de pruebas.');
        }

        $sqlitePath = storage_path('framework/testing-backup-rotation.sqlite');
        if (! is_dir(dirname($sqlitePath))) {
            mkdir(dirname($sqlitePath), 0775, true);
        }
        file_put_contents($sqlitePath, 'sqlite-backup-rotation');

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', $sqlitePath);

        $documentsDir = storage_path('app/public/documents/testing-rotation');
        if (! is_dir($documentsDir)) {
            mkdir($documentsDir, 0775, true);
        }
        file_put_contents($documentsDir.'/evidence.txt', 'demo-evidence');

        $this->deleteDirectory(storage_path('app/backups'));

        $this->artisan('inmo:backup --keep=1')->assertExitCode(0);
        usleep(1100000);
        $this->artisan('inmo:backup --keep=1')->assertExitCode(0);

        $snapshots = collect(scandir(storage_path('app/backups')) ?: [])
            ->filter(fn ($entry) => ! in_array($entry, ['.', '..'], true))
            ->values();

        $this->assertCount(1, $snapshots);
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = array_diff(scandir($directory) ?: [], ['.', '..']);

        foreach ($items as $item) {
            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);

                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
