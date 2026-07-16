<?php

namespace App\Actions\Charges;

use App\Actions\Payments\ApplyCreditBalanceAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

class GenerateMonthlyRentChargesAction
{
    /**
     * @return array{created:int, skipped:int, month:string}
     */
    public function execute(string $month): array
    {
        return $this->executeForOrganization($month, null);
    }

    /**
     * @return array{created:int, skipped:int, month:string}
     */
    public function executeForOrganization(string $month, ?int $organizationId): array
    {
        $periodStart = CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth();
        $periodEnd = $periodStart->endOfMonth();

        $created = 0;
        $skipped = 0;

        $contractsQuery = Contract::query()
            ->withoutOrganizationScope()
            ->where('status', Contract::STATUS_ACTIVE)
            ->whereDate('starts_at', '<=', $periodEnd->toDateString())
            ->where(function ($query) use ($periodStart): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', $periodStart->toDateString());
            });

        if (is_int($organizationId) && $organizationId > 0) {
            $contractsQuery->where('organization_id', $organizationId);
        }

        $contractsQuery->orderBy('id')
            ->chunkById(200, function ($contracts) use (&$created, &$skipped, $periodStart): void {
                foreach ($contracts as $contract) {
                    $charge = $this->createRentChargeForContractPeriod($contract, $periodStart);

                    if ($charge->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $skipped++;
                    }
                }
            });

        return [
            'created' => $created,
            'skipped' => $skipped,
            'month' => $month,
        ];
    }

    public function ensureCurrentMonthForContract(Contract $contract): Charge
    {
        $currentMonth = now('America/Tijuana')->format('Y-m');
        $periodStart = CarbonImmutable::createFromFormat('Y-m', $currentMonth)->startOfMonth();

        return $this->createRentChargeForContractPeriod($contract, $periodStart);
    }

    private function createRentChargeForContractPeriod(Contract $contract, CarbonImmutable $periodStart): Charge
    {
        $month = $periodStart->format('Y-m');
        $dueDate = $this->buildDueDate($periodStart, (int) $contract->due_day);
        $graceUntil = $dueDate->addDays(max((int) $contract->grace_days, 0));

        $attributes = [
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'type' => Charge::TYPE_RENT,
            'period' => $month,
        ];

        $charge = Charge::query()->withoutOrganizationScope()->where($attributes)->first();

        if ($charge === null) {
            try {
                $charge = Charge::query()->withoutOrganizationScope()->create($attributes + [
                    'unit_id' => $contract->unit_id,
                    'charge_date' => $periodStart->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                    'grace_until' => $graceUntil->toDateString(),
                    'amount' => $contract->rent_amount,
                    'meta' => [],
                ]);
            } catch (QueryException $exception) {
                if (! $this->isDuplicateRentViolation($exception)) {
                    throw $exception;
                }

                // Another process won the race and inserted the same RENT
                // charge between our lookup and our insert; re-fetch it.
                $charge = Charge::query()->withoutOrganizationScope()->where($attributes)->first();

                if ($charge === null) {
                    throw $exception;
                }
            }
        }

        $this->applyCreditBalance($contract);

        return $charge;
    }

    private function isDuplicateRentViolation(QueryException $exception): bool
    {
        if ($exception->getCode() !== '23000') {
            return false;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'charges_contract_rent_period_key_unique')
            || str_contains($message, 'rent_period_key')
            || str_contains($message, 'unique');
    }

    private function applyCreditBalance(Contract $contract): void
    {
        $previousOrganizationId = TenantContext::currentOrganizationId();
        TenantContext::setOrganizationId($contract->organization_id);

        try {
            app(ApplyCreditBalanceAction::class)->execute($contract);
        } finally {
            TenantContext::setOrganizationId($previousOrganizationId);
        }
    }

    private function buildDueDate(CarbonImmutable $periodStart, int $dueDay): CarbonImmutable
    {
        $normalizedDueDay = max(1, $dueDay);
        $day = min($normalizedDueDay, $periodStart->daysInMonth);

        return $periodStart->day($day);
    }
}
