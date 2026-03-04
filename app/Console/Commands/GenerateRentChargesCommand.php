<?php

namespace App\Console\Commands;

use App\Actions\Charges\GenerateMonthlyRentChargesAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GenerateRentChargesCommand extends Command
{
    private const LOCK_KEY = 'commands:inmo:generate-rent';

    private const LOCK_TTL_SECONDS = 600;

    /**
     * @var string
     */
    protected $signature = 'inmo:generate-rent {--month= : Mes objetivo en formato YYYY-MM}';

    /**
     * @var string
     */
    protected $description = 'Genera cargos de renta mensual para contratos activos de forma idempotente';

    public function __construct(
        private readonly GenerateMonthlyRentChargesAction $action
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

        $month = (string) $this->option('month');

        try {
            if (! $this->isValidMonth($month)) {
                $this->error('Debes enviar --month con formato YYYY-MM. Ejemplo: --month=2026-03');

                return self::FAILURE;
            }

            $result = $this->action->execute($month);

            $this->info("Generación de rentas completada para {$result['month']}.");
            $this->line("Cargos creados: {$result['created']}");
            $this->line("Cargos omitidos (ya existentes): {$result['skipped']}");

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    private function isValidMonth(string $month): bool
    {
        if ($month === '') {
            return false;
        }

        return ! Validator::make(
            ['month' => $month],
            ['month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]
        )->fails();
    }
}
