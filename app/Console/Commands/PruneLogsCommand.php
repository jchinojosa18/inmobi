<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PruneLogsCommand extends Command
{
    private const LOCK_KEY = 'commands:inmo:logs:prune';

    private const LOCK_TTL_SECONDS = 3600;

    private const DEFAULT_CHUNK_SIZE = 2000;

    protected $signature = 'inmo:logs:prune
        {--auth-days= : Retención de auth_events en días (default config)}
        {--audit-days= : Retención de audit_events en días (default config)}
        {--dry-run : Solo muestra cuántos registros eliminaría}
        {--before= : Elimina todo lo anterior a YYYY-MM-DD e ignora days}';

    protected $description = 'Limpia logs antiguos de auth_events y audit_events según ventana de retención.';

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

        try {
            $dryRun = (bool) $this->option('dry-run');
            $beforeOption = trim((string) $this->option('before'));
            $appTimezone = (string) config('app.timezone', 'UTC');
            $localTimezone = 'America/Tijuana';

            if ($beforeOption !== '') {
                if (! $this->isValidDate($beforeOption)) {
                    $this->error('Debes enviar --before con formato YYYY-MM-DD. Ejemplo: --before=2026-01-01');

                    return self::FAILURE;
                }

                $beforeDate = CarbonImmutable::createFromFormat('Y-m-d', $beforeOption, $localTimezone)?->startOfDay();

                if ($beforeDate === null) {
                    $this->error('No se pudo interpretar la fecha enviada en --before.');

                    return self::FAILURE;
                }

                $authCutoff = $beforeDate->setTimezone($appTimezone);
                $auditCutoff = $beforeDate->setTimezone($appTimezone);
            } else {
                $authDays = $this->resolveRetentionDays('auth-days', (int) config('audit.prune.auth_days', 90));
                $auditDays = $this->resolveRetentionDays('audit-days', (int) config('audit.prune.audit_days', 180));

                if ($authDays === null || $auditDays === null) {
                    return self::FAILURE;
                }

                $nowLocal = CarbonImmutable::now($localTimezone);
                $authCutoff = $nowLocal->subDays($authDays)->setTimezone($appTimezone);
                $auditCutoff = $nowLocal->subDays($auditDays)->setTimezone($appTimezone);
            }

            $auth = $this->pruneTable('auth_events', $authCutoff, $dryRun);
            $audit = $this->pruneTable('audit_events', $auditCutoff, $dryRun);

            if ($dryRun) {
                $this->line("Auth events deleted: 0 (dry-run, would delete: {$auth['matched']})");
                $this->line("Audit events deleted: 0 (dry-run, would delete: {$audit['matched']})");
            } else {
                $this->line("Auth events deleted: {$auth['deleted']}");
                $this->line("Audit events deleted: {$audit['deleted']}");
            }

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    private function resolveRetentionDays(string $option, int $default): ?int
    {
        $value = trim((string) $this->option($option));

        if ($value === '') {
            return max(1, $default);
        }

        if (! ctype_digit($value)) {
            $this->error("--{$option} debe ser un entero positivo.");

            return null;
        }

        $days = (int) $value;
        if ($days < 1) {
            $this->error("--{$option} debe ser mayor o igual a 1.");

            return null;
        }

        return $days;
    }

    private function isValidDate(string $value): bool
    {
        return ! Validator::make(
            ['date' => $value],
            ['date' => ['required', 'date_format:Y-m-d']]
        )->fails();
    }

    /**
     * @return array{matched:int,deleted:int}
     */
    private function pruneTable(string $table, CarbonImmutable $cutoff, bool $dryRun): array
    {
        $baseQuery = DB::table($table)
            ->where('occurred_at', '<', $cutoff->toDateTimeString());

        $matched = (int) (clone $baseQuery)->count();

        if ($dryRun || $matched === 0) {
            return [
                'matched' => $matched,
                'deleted' => 0,
            ];
        }

        $deleted = 0;

        while (true) {
            $ids = (clone $baseQuery)
                ->orderBy('id')
                ->limit(self::DEFAULT_CHUNK_SIZE)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            if ($ids === []) {
                break;
            }

            $deleted += DB::table($table)
                ->whereIn('id', $ids)
                ->delete();
        }

        return [
            'matched' => $matched,
            'deleted' => $deleted,
        ];
    }
}
