<?php

namespace App\Console\Commands;

use App\Actions\Charges\GenerateMonthlyRentChargesAction;
use App\Actions\Contracts\ProcessContractSettlementAction;
use App\Actions\Contracts\RegisterDepositHoldAction;
use App\Actions\MonthCloses\CloseMonthAction;
use App\Actions\Payments\RegisterContractPaymentAction;
use App\Actions\Penalties\RunDailyPenaltiesAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Support\DepositBalanceService;
use App\Support\OperatingIncomeService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class InmoSmokeCommand extends Command
{
    protected $signature = 'inmo:smoke {--date= : Fecha base YYYY-MM-DD (default hoy America/Tijuana)}';

    protected $description = 'Ejecuta un smoke test end-to-end sin UI sobre flujo de rentas, multas, pagos, cierre y finiquito';

    public function __construct(
        private readonly GenerateMonthlyRentChargesAction $rentAction,
        private readonly RunDailyPenaltiesAction $penaltyAction,
        private readonly RegisterContractPaymentAction $registerPaymentAction,
        private readonly RegisterDepositHoldAction $registerDepositHoldAction,
        private readonly ProcessContractSettlementAction $settlementAction,
        private readonly CloseMonthAction $closeMonthAction,
        private readonly OperatingIncomeService $operatingIncomeService,
        private readonly DepositBalanceService $depositBalanceService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $targetDate = $this->resolveDate((string) $this->option('date'));
        if ($targetDate === null) {
            $this->error('Debes enviar --date con formato YYYY-MM-DD. Ejemplo: --date=2026-03-10');

            return self::FAILURE;
        }

        $organization = Organization::query()->where('name', DemoDataSeeder::ORGANIZATION_NAME)->first();
        if ($organization === null) {
            $this->error('No hay datos demo. Ejecuta: php artisan db:seed --class=DemoDataSeeder');

            return self::FAILURE;
        }

        $adminUser = User::query()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->first();

        if ($adminUser === null) {
            $this->error('No se encontró usuario para cerrar mes en la organización demo.');

            return self::FAILURE;
        }

        TenantContext::setOrganizationId((int) $organization->id);

        try {
            $this->line('== Smoke: '.DemoDataSeeder::ORGANIZATION_NAME.' ==');
            $this->line('Fecha base: '.$targetDate->toDateString());

            $currentMonth = $targetDate->format('Y-m');
            $previousMonth = $targetDate->subMonthNoOverflow()->format('Y-m');

            $rentResult = $this->rentAction->execute($currentMonth);
            $this->line("Rentas {$currentMonth}: creados={$rentResult['created']} omitidos={$rentResult['skipped']}");

            $backfillStart = $targetDate->subDays(5);
            $penaltiesBeforeBackfill = $this->penaltyCountForOrganization((int) $organization->id);
            $this->penaltyAction->execute($targetDate, $backfillStart);
            $penaltiesCreatedBackfill = $this->penaltyCountForOrganization((int) $organization->id) - $penaltiesBeforeBackfill;
            $this->line(
                "Multas backfill {$backfillStart->toDateString()} -> {$targetDate->toDateString()}: "
                ."creadas={$penaltiesCreatedBackfill}"
            );

            $contractA = $this->findContractA((int) $organization->id);
            if ($contractA === null) {
                $this->error('No se encontró Contrato A del set demo.');

                return self::FAILURE;
            }

            $partialPayment = $this->registerPaymentAction->execute($contractA, [
                'amount' => 3000,
                'method' => Payment::METHOD_TRANSFER,
                'paid_at' => $targetDate->setTime(12, 0)->toDateTimeString(),
                'reference' => 'SMOKE-A-PARTIAL-'.$targetDate->format('Ymd'),
            ]);
            $this->line("Pago parcial A registrado: {$partialPayment->receipt_folio} (\${$partialPayment->amount})");

            $nextDay = $targetDate->addDay();
            $penaltiesBeforeNextDay = $this->penaltyCountForOrganization((int) $organization->id);
            $this->penaltyAction->execute($nextDay, $nextDay);
            $penaltiesCreatedNextDay = $this->penaltyCountForOrganization((int) $organization->id) - $penaltiesBeforeNextDay;
            $baseReductionText = $this->summarizePenaltyBaseTrend($contractA->id, $targetDate, $nextDay);
            $this->line("Multas día siguiente {$nextDay->toDateString()}: creadas={$penaltiesCreatedNextDay} | {$baseReductionText}");

            $monthClose = $this->closeMonthAction->execute(
                organizationId: (int) $organization->id,
                userId: (int) $adminUser->id,
                month: $previousMonth,
                notes: 'Smoke test automático',
            );
            $this->line("Mes anterior cerrado/verificado: {$monthClose->month}");

            $contractB = $this->findContractB((int) $organization->id);
            if ($contractB !== null && $contractB->status === Contract::STATUS_ACTIVE) {
                $settlementResult = $this->runSettlementForContractB(
                    contract: $contractB,
                    targetDate: $targetDate,
                    userId: (int) $adminUser->id,
                );

                $this->line(
                    "Finiquito B: batch={$settlementResult['batch_id']} "
                    .'aplicado_deposito=$'.number_format((float) $settlementResult['deposit_applied'], 2)
                    .' devolucion=$'.number_format((float) $settlementResult['deposit_refund'], 2)
                );
            } else {
                $this->line('Finiquito B omitido: contrato no activo o inexistente.');
            }

            $this->printTotals(
                organizationId: (int) $organization->id,
                from: $targetDate->subMonthNoOverflow()->startOfMonth(),
                to: $targetDate->addDay()->endOfDay(),
            );
        } finally {
            TenantContext::clear();
        }

        return self::SUCCESS;
    }

    private function resolveDate(string $option): ?CarbonImmutable
    {
        if ($option === '') {
            return CarbonImmutable::now('America/Tijuana')->startOfDay();
        }

        $validator = Validator::make(
            ['date' => $option],
            ['date' => ['required', 'date_format:Y-m-d']]
        );

        if ($validator->fails()) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $option, 'America/Tijuana')
            ?->startOfDay();
    }

    private function findContractA(int $organizationId): ?Contract
    {
        return Contract::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->where('due_day', 1)
            ->where('rent_amount', 10000)
            ->orderBy('id')
            ->first();
    }

    private function findContractB(int $organizationId): ?Contract
    {
        return Contract::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->where('due_day', 15)
            ->where('rent_amount', 8000)
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array{batch_id:string, deposit_applied:float, deposit_refund:float}
     */
    private function runSettlementForContractB(Contract $contract, CarbonImmutable $targetDate, int $userId): array
    {
        $outstanding = $this->depositBalanceService->outstandingBalanceExcludingDepositHold($contract);
        if ($outstanding > 0) {
            $this->registerPaymentAction->execute($contract, [
                'amount' => $outstanding,
                'method' => Payment::METHOD_TRANSFER,
                'paid_at' => $targetDate->setTime(13, 0)->toDateTimeString(),
                'reference' => 'SMOKE-B-CLEAR-'.$targetDate->format('Ymd'),
            ]);
        }

        $depositHold = $this->registerDepositHoldAction->execute(
            contract: $contract,
            amount: (float) $contract->deposit_amount,
            receivedAt: $targetDate->toDateString(),
            notes: 'Depósito smoke',
            userId: $userId,
        );

        $pendingDepositHold = $this->pendingChargeBalance($depositHold);
        if ($pendingDepositHold > 0) {
            $this->registerPaymentAction->execute($contract, [
                'amount' => $pendingDepositHold,
                'method' => Payment::METHOD_TRANSFER,
                'paid_at' => $targetDate->setTime(14, 0)->toDateTimeString(),
                'reference' => 'SMOKE-B-DEPOSIT-'.$targetDate->format('Ymd'),
            ]);
        }

        $result = $this->settlementAction->execute(
            contract: $contract,
            moveOutDate: $targetDate->toDateString(),
            concepts: [
                [
                    'description' => 'Limpieza finiquito smoke',
                    'amount' => 1000,
                ],
            ],
            userId: $userId,
        );

        return [
            'batch_id' => $result->batchId,
            'deposit_applied' => $result->depositApplied,
            'deposit_refund' => $result->depositRefund,
        ];
    }

    private function pendingChargeBalance(Charge $charge): float
    {
        $allocated = (float) PaymentAllocation::query()
            ->withoutOrganizationScope()
            ->where('charge_id', $charge->id)
            ->sum('amount');

        return round(max((float) $charge->amount - $allocated, 0), 2);
    }

    private function summarizePenaltyBaseTrend(int $contractId, CarbonImmutable $targetDate, CarbonImmutable $nextDay): string
    {
        $targetPenalty = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contractId)
            ->where('type', Charge::TYPE_PENALTY)
            ->whereDate('penalty_date', $targetDate->toDateString())
            ->first();

        $nextPenalty = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contractId)
            ->where('type', Charge::TYPE_PENALTY)
            ->whereDate('penalty_date', $nextDay->toDateString())
            ->first();

        $baseTarget = (float) data_get($targetPenalty?->meta, 'base_amount', 0);
        $baseNext = (float) data_get($nextPenalty?->meta, 'base_amount', 0);

        if ($baseTarget <= 0 || $baseNext <= 0) {
            return 'sin datos comparables de base';
        }

        if ($baseNext < $baseTarget) {
            return 'base reducida tras pago parcial';
        }

        return 'base no reducida (revisar monto de pago)';
    }

    private function printTotals(int $organizationId, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $charges = Charge::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->count();

        $penalties = Charge::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->where('type', Charge::TYPE_PENALTY)
            ->count();

        $payments = Payment::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->count();

        $allocations = PaymentAllocation::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->count();

        $expenses = Expense::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->count();

        $operatingIncome = $this->operatingIncomeService->sumForRange(
            organizationId: $organizationId,
            dateFrom: $from->startOfDay(),
            dateTo: $to->endOfDay(),
        );

        $expenseTotal = (float) Expense::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->whereBetween('spent_at', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        $this->newLine();
        $this->info('--- Resumen smoke ---');
        $this->line("Rango: {$from->toDateString()} a {$to->toDateString()}");
        $this->line("#charges: {$charges}");
        $this->line("#penalties: {$penalties}");
        $this->line("#payments: {$payments}");
        $this->line("#allocations: {$allocations}");
        $this->line("#expenses: {$expenses}");
        $this->line('Ingresos operativos (allocations): $'.number_format($operatingIncome, 2));
        $this->line('Egresos: $'.number_format($expenseTotal, 2));
    }

    private function penaltyCountForOrganization(int $organizationId): int
    {
        return Charge::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->where('type', Charge::TYPE_PENALTY)
            ->count();
    }
}
