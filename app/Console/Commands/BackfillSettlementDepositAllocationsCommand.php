<?php

namespace App\Console\Commands;

use App\Actions\Payments\ApplyDepositToOutstandingAction;
use App\Models\Contract;
use App\Models\Payment;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class BackfillSettlementDepositAllocationsCommand extends Command
{
    protected $signature = 'inmo:settlements:backfill-deposit-allocations
        {--contract= : Limita a un contract_id}
        {--organization-id= : Limita a una organization_id}
        {--dry-run : Solo reporta lo que haría}';

    protected $description = 'Crea Payment DEPOSIT + allocations faltantes para finiquitos con depósito aplicado (Salida sin saldo).';

    public function handle(ApplyDepositToOutstandingAction $applyDeposit): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = Contract::query()
            ->withoutOrganizationScope()
            ->where('status', Contract::STATUS_ENDED)
            ->whereNotNull('meta->settlement_batch_id')
            ->orderBy('id');

        if (is_numeric($this->option('contract'))) {
            $query->whereKey((int) $this->option('contract'));
        }

        if (is_numeric($this->option('organization-id'))) {
            $query->where('organization_id', (int) $this->option('organization-id'));
        }

        $applied = 0;
        $skipped = 0;
        $noop = 0;

        foreach ($query->cursor() as $contract) {
            $settlements = data_get($contract->meta, 'settlements');
            if (! is_array($settlements) || $settlements === []) {
                $skipped++;

                continue;
            }

            foreach ($settlements as $batchId => $settlement) {
                if (! is_array($settlement)) {
                    continue;
                }

                $batchId = (string) ($settlement['batch_id'] ?? $batchId);
                $depositApplied = round((float) ($settlement['deposit_applied'] ?? 0), 2);

                if ($batchId === '' || $depositApplied <= 0) {
                    continue;
                }

                $existing = Payment::query()
                    ->withoutOrganizationScope()
                    ->where('organization_id', $contract->organization_id)
                    ->where('contract_id', $contract->id)
                    ->where('method', Payment::METHOD_DEPOSIT)
                    ->where('meta->settlement_batch_id', $batchId)
                    ->exists();

                if ($existing) {
                    $this->line("skip contract #{$contract->id} batch {$batchId} (already has DEPOSIT payment)");
                    $skipped++;

                    continue;
                }

                $moveOutDate = (string) ($settlement['move_out_date'] ?? $contract->ends_at?->toDateString() ?? now('America/Tijuana')->toDateString());
                $paidAt = CarbonImmutable::parse($moveOutDate, 'America/Tijuana')->startOfDay();

                if ($dryRun) {
                    $this->line("dry-run contract #{$contract->id} batch {$batchId} deposit_applied={$depositApplied}");
                    $noop++;

                    continue;
                }

                TenantContext::setOrganizationId((int) $contract->organization_id);

                try {
                    $result = $applyDeposit->execute(
                        contract: $contract,
                        amount: $depositApplied,
                        settlementBatchId: $batchId,
                        paidAt: $paidAt,
                    );

                    if ($result->paymentId === null || $result->appliedAmount <= 0) {
                        $this->line("noop contract #{$contract->id} batch {$batchId} (no pending to allocate)");
                        $noop++;
                    } else {
                        $this->info("applied contract #{$contract->id} batch {$batchId} amount={$result->appliedAmount} allocations={$result->allocationsCount} payment=#{$result->paymentId}");
                        $applied++;
                    }
                } finally {
                    TenantContext::clear();
                }
            }
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."Done. applied={$applied} skipped={$skipped} noop={$noop}");

        return self::SUCCESS;
    }
}
