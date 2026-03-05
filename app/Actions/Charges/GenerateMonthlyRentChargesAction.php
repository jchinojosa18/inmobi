<?php

namespace App\Actions\Charges;

use App\Models\Charge;
use App\Models\Contract;
use Carbon\CarbonImmutable;

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

        return Charge::query()
            ->withoutOrganizationScope()
            ->firstOrCreate(
                [
                    'organization_id' => $contract->organization_id,
                    'contract_id' => $contract->id,
                    'type' => Charge::TYPE_RENT,
                    'period' => $month,
                ],
                [
                    'unit_id' => $contract->unit_id,
                    'charge_date' => $periodStart->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                    'grace_until' => $graceUntil->toDateString(),
                    'amount' => $contract->rent_amount,
                    'meta' => [],
                ]
            );
    }

    private function buildDueDate(CarbonImmutable $periodStart, int $dueDay): CarbonImmutable
    {
        $normalizedDueDay = max(1, $dueDay);
        $day = min($normalizedDueDay, $periodStart->daysInMonth);

        return $periodStart->day($day);
    }
}
