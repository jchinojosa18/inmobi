<?php

namespace App\Console\Commands;

use App\Actions\Penalties\RunDailyPenaltiesAction;
use App\Models\Charge;
use App\Models\Contract;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RebuildPenaltiesCommand extends Command
{
    private const LOCK_KEY = 'commands:inmo:penalties:rebuild';

    private const LOCK_TTL_SECONDS = 900;

    protected $signature = 'inmo:penalties:rebuild
        {--contract= : ID del contrato objetivo}
        {--from= : Fecha inicial (YYYY-MM-DD)}
        {--to= : Fecha final (YYYY-MM-DD)}';

    protected $description = 'Reconstruye multas PENALTY en un rango para un contrato: borra y regenera.';

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
            $contractId = (int) $this->option('contract');
            $from = (string) $this->option('from');
            $to = (string) $this->option('to');

            if ($contractId <= 0) {
                $this->error('Debes enviar --contract con un ID válido.');

                return self::FAILURE;
            }

            $fromDate = $this->parseDate($from, '--from');
            $toDate = $this->parseDate($to, '--to');
            if ($fromDate === null || $toDate === null) {
                return self::FAILURE;
            }

            if ($fromDate->gt($toDate)) {
                $this->error('--from no puede ser mayor que --to.');

                return self::FAILURE;
            }

            $contract = Contract::query()
                ->withoutOrganizationScope()
                ->find($contractId);

            if ($contract === null) {
                $this->error("No se encontró contrato #{$contractId}.");

                return self::FAILURE;
            }

            $deleted = DB::transaction(function () use ($contract, $fromDate, $toDate): int {
                $penaltyIds = DB::table('charges')
                    ->where('organization_id', $contract->organization_id)
                    ->where('contract_id', $contract->id)
                    ->where('type', Charge::TYPE_PENALTY)
                    ->whereDate('penalty_date', '>=', $fromDate->toDateString())
                    ->whereDate('penalty_date', '<=', $toDate->toDateString())
                    ->pluck('id')
                    ->map(static fn ($id): int => (int) $id)
                    ->all();

                if ($penaltyIds === []) {
                    return 0;
                }

                DB::table('payment_allocations')
                    ->whereIn('charge_id', $penaltyIds)
                    ->delete();

                return DB::table('charges')
                    ->whereIn('id', $penaltyIds)
                    ->delete();
            });

            $result = $this->action->execute(
                targetDate: $toDate,
                fromDate: $fromDate,
                contractId: $contract->id
            );

            $this->info("Penalties rebuild completado para contrato #{$contract->id}");
            $this->line("Rango: {$fromDate->toDateString()} -> {$toDate->toDateString()}");
            $this->line("Penalties borradas: {$deleted}");
            $this->line("Penalties creadas: {$result['created']}");
            $this->line("Penalties existentes (idempotencia): {$result['skipped_existing']}");
            $this->line("No aplicables: {$result['skipped_not_applicable']}");

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    private function parseDate(string $value, string $optionName): ?CarbonImmutable
    {
        $validation = Validator::make(
            ['date' => $value],
            ['date' => ['required', 'date_format:Y-m-d']]
        );

        if ($validation->fails()) {
            $this->error("Debes enviar {$optionName} con formato YYYY-MM-DD.");

            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $value, 'America/Tijuana')
            ?->startOfDay();
    }
}
