<?php

namespace App\Console\Commands;

use App\Actions\Charges\RegisterContractAdjustmentAction;
use App\Models\Charge;
use App\Support\TenantContext;
use Illuminate\Console\Command;

class SettleNegativeAdjustmentCreditsCommand extends Command
{
    protected $signature = 'inmo:adjustments:settle-negative-credits
        {--contract-id= : Limita a un contract_id}
        {--organization-id= : Limita a una organization_id}';

    protected $description = 'Convierte ADJUSTMENT negativos huérfanos en credit_balances (idempotente)';

    public function handle(RegisterContractAdjustmentAction $action): int
    {
        $query = Charge::query()
            ->withoutOrganizationScope()
            ->where('type', Charge::TYPE_ADJUSTMENT)
            ->where('amount', '<', 0)
            ->whereNull('deleted_at')
            ->where(function ($q): void {
                $q->whereNull('meta->settled_as_credit')
                    ->orWhere('meta->settled_as_credit', false);
            })
            ->orderBy('id');

        if (is_numeric($this->option('contract-id'))) {
            $query->where('contract_id', (int) $this->option('contract-id'));
        }
        if (is_numeric($this->option('organization-id'))) {
            $query->where('organization_id', (int) $this->option('organization-id'));
        }

        $settled = 0;
        $skipped = 0;

        foreach ($query->cursor() as $charge) {
            TenantContext::setOrganizationId((int) $charge->organization_id);

            try {
                if ($action->settleExistingNegativeAdjustment($charge)) {
                    $settled++;
                    $this->line("Settled charge #{$charge->id} contract #{$charge->contract_id}");
                } else {
                    $skipped++;
                }
            } finally {
                TenantContext::clear();
            }
        }

        $this->info("Done. settled={$settled} skipped={$skipped}");

        return self::SUCCESS;
    }
}
