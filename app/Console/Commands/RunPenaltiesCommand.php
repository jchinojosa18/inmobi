<?php

namespace App\Console\Commands;

use App\Actions\Penalties\RunDailyPenaltiesAction;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RunPenaltiesCommand extends Command
{
    private const LOCK_KEY = 'commands:inmo:penalties:run';

    private const LOCK_TTL_SECONDS = 600;

    protected $signature = 'inmo:penalties:run
        {--date= : Fecha objetivo en formato YYYY-MM-DD (default hoy en America/Tijuana)}
        {--from-date= : Fecha inicial opcional para limitar backfill (YYYY-MM-DD)}';

    protected $description = 'Genera multas diarias con interés compuesto para contratos con saldo vencido';

    public function __construct(
        private readonly RunDailyPenaltiesAction $action
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

        try {
            $targetDate = $this->resolveTargetDate((string) $this->option('date'));
            $fromDateOption = (string) $this->option('from-date');
            $fromDate = $fromDateOption === '' ? null : $this->resolveTargetDate($fromDateOption);

            if ($targetDate === null) {
                $this->error('Debes enviar --date con formato YYYY-MM-DD. Ejemplo: --date=2026-03-04');

                return self::FAILURE;
            }

            if ($fromDateOption !== '' && $fromDate === null) {
                $this->error('Debes enviar --from-date con formato YYYY-MM-DD. Ejemplo: --from-date=2026-03-01');

                return self::FAILURE;
            }

            if ($fromDate !== null && $fromDate->gt($targetDate)) {
                $this->error('--from-date no puede ser mayor que --date.');

                return self::FAILURE;
            }

            $result = $this->action->execute($targetDate, $fromDate);

            $this->info("Multas procesadas para {$result['target_date']}");
            if (is_string($result['from_date']) && $result['from_date'] !== '') {
                $this->line("Inicio de cálculo: {$result['from_date']}");
            }
            $this->line("Contratos evaluados: {$result['contracts_processed']}");
            $this->line("Días evaluados: {$result['days_evaluated']}");
            $this->line("Multas creadas: {$result['created']}");
            $this->line("Omitidas por idempotencia: {$result['skipped_existing']}");
            $this->line("Omitidas por no aplicar: {$result['skipped_not_applicable']}");

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    private function resolveTargetDate(string $dateOption): ?CarbonImmutable
    {
        if ($dateOption === '') {
            return CarbonImmutable::now('America/Tijuana')->startOfDay();
        }

        $validation = Validator::make(
            ['date' => $dateOption],
            ['date' => ['required', 'date_format:Y-m-d']]
        );

        if ($validation->fails()) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $dateOption, 'America/Tijuana')
            ?->startOfDay();
    }
}
