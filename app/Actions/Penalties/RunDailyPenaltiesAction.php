<?php

namespace App\Actions\Penalties;

use App\Actions\Payments\ApplyCreditBalanceAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\PaymentAllocation;
use App\Support\LedgerOutstandingCalculator;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class RunDailyPenaltiesAction
{
    private const TIMEZONE = 'America/Tijuana';

    private const ALGORITHM_VERSION = 'v1_compound_daily';

    public function __construct(
        private readonly LedgerOutstandingCalculator $ledgerOutstandingCalculator,
    ) {}

    /**
     * @return array{target_date:string, from_date:?string, contract_id:?int, contracts_processed:int, days_evaluated:int, created:int, skipped_existing:int, skipped_not_applicable:int}
     */
    public function execute(CarbonImmutable $targetDate, ?CarbonImmutable $fromDate = null, ?int $contractId = null): array
    {
        $targetDate = $targetDate->setTimezone(self::TIMEZONE)->startOfDay();
        $fromDate = $fromDate?->setTimezone(self::TIMEZONE)->startOfDay();

        $stats = [
            'target_date' => $targetDate->toDateString(),
            'from_date' => $fromDate?->toDateString(),
            'contract_id' => $contractId,
            'contracts_processed' => 0,
            'days_evaluated' => 0,
            'created' => 0,
            'skipped_existing' => 0,
            'skipped_not_applicable' => 0,
        ];

        $contractsQuery = Contract::query()
            ->withoutOrganizationScope()
            ->where('penalty_rate_daily', '>', 0)
            ->orderBy('id');

        if ($contractId !== null) {
            $contractsQuery->whereKey($contractId);
        }

        $contractsQuery->chunkById(200, function ($contracts) use ($targetDate, $fromDate, &$stats): void {
            foreach ($contracts as $contract) {
                $stats['contracts_processed']++;

                $startDate = $this->resolveStartDate($contract, $targetDate, $fromDate);
                if ($startDate === null || $startDate->gt($targetDate)) {
                    continue;
                }

                for ($cursor = $startDate; $cursor->lte($targetDate); $cursor = $cursor->addDay()) {
                    $stats['days_evaluated']++;

                    $result = $this->runForContractDate($contract, $cursor);

                    if ($result === 'created') {
                        $stats['created']++;

                        continue;
                    }

                    if ($result === 'existing') {
                        $stats['skipped_existing']++;

                        continue;
                    }

                    $stats['skipped_not_applicable']++;
                }
            }
        });

        return $stats;
    }

    private function resolveStartDate(Contract $contract, CarbonImmutable $targetDate, ?CarbonImmutable $fromDate): ?CarbonImmutable
    {
        $lastPenaltyDate = Charge::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $contract->organization_id)
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_PENALTY)
            ->whereNotNull('penalty_date')
            ->max('penalty_date');

        $startDate = null;

        if (is_string($lastPenaltyDate) && $lastPenaltyDate !== '') {
            $startDate = CarbonImmutable::parse($lastPenaltyDate, self::TIMEZONE)
                ->addDay()
                ->startOfDay();
        } else {
            $firstGraceUntil = Charge::query()
                ->withoutOrganizationScope()
                ->where('organization_id', $contract->organization_id)
                ->where('contract_id', $contract->id)
                ->where('type', Charge::TYPE_RENT)
                ->whereNotNull('grace_until')
                ->whereDate('charge_date', '<=', $targetDate->toDateString())
                ->min('grace_until');

            if (is_string($firstGraceUntil) && $firstGraceUntil !== '') {
                $startDate = CarbonImmutable::parse($firstGraceUntil, self::TIMEZONE)
                    ->addDay()
                    ->startOfDay();
            }
        }

        if ($startDate === null) {
            return null;
        }

        if ($fromDate !== null && $startDate->lt($fromDate)) {
            return $fromDate;
        }

        return $startDate;
    }

    private function runForContractDate(Contract $contract, CarbonImmutable $penaltyDate): string
    {
        return DB::transaction(function () use ($contract, $penaltyDate): string {
            $lockedContract = Contract::query()
                ->withoutOrganizationScope()
                ->where('id', $contract->id)
                ->lockForUpdate()
                ->first();

            if ($lockedContract === null) {
                return 'not_applicable';
            }

            $existingPenalty = Charge::query()
                ->withoutOrganizationScope()
                ->where('organization_id', $lockedContract->organization_id)
                ->where('contract_id', $lockedContract->id)
                ->where('type', Charge::TYPE_PENALTY)
                ->whereDate('penalty_date', $penaltyDate->toDateString())
                ->lockForUpdate()
                ->exists();

            if ($existingPenalty) {
                return 'existing';
            }

            $cutoffDate = $penaltyDate->subDay()->startOfDay();
            $cutoffTimestampLocal = $cutoffDate->setTime(23, 59, 59);
            $cutoffTimestampStorage = $this->toStorageTimezone($cutoffTimestampLocal);

            if (! $this->hasOverdueRentBalance($lockedContract, $cutoffDate, $cutoffTimestampStorage)) {
                return 'not_applicable';
            }

            $baseAmount = $this->calculateOverdueBalance(
                $lockedContract,
                $cutoffDate,
                $cutoffTimestampStorage,
            );

            if ($baseAmount <= 0) {
                return 'not_applicable';
            }

            $rateDaily = round((float) $lockedContract->penalty_rate_daily, 6);
            if ($rateDaily <= 0) {
                return 'not_applicable';
            }

            $computedAmount = round($baseAmount * $rateDaily, 2);
            if ($computedAmount <= 0) {
                return 'not_applicable';
            }

            $sourceRentPeriod = $this->resolveSourceRentPeriod(
                $lockedContract,
                $cutoffDate,
                $cutoffTimestampStorage,
            );

            $meta = [
                'base_amount' => $baseAmount,
                'rate_daily' => $rateDaily,
                'computed_amount' => $computedAmount,
                'algorithm_version' => self::ALGORITHM_VERSION,
                'cutoff_timestamp' => $cutoffTimestampLocal->toIso8601String(),
                'cutoff_timestamp_storage' => $cutoffTimestampStorage->toIso8601String(),
                'source_rent_period' => $sourceRentPeriod,
            ];

            try {
                Charge::query()
                    ->withoutOrganizationScope()
                    ->create([
                        'organization_id' => $lockedContract->organization_id,
                        'contract_id' => $lockedContract->id,
                        'unit_id' => $lockedContract->unit_id,
                        'type' => Charge::TYPE_PENALTY,
                        'period' => null,
                        'charge_date' => $penaltyDate->toDateString(),
                        'due_date' => null,
                        'grace_until' => null,
                        'penalty_date' => $penaltyDate->toDateString(),
                        'amount' => $computedAmount,
                        'meta' => $meta,
                    ]);
            } catch (QueryException $exception) {
                if (! $this->isDuplicatePenaltyViolation($exception)) {
                    throw $exception;
                }

                // Rebuild flows soft-delete the previous penalty for the same day before
                // recomputing it; the unique index still reserves that (contract, day, type)
                // slot for the trashed row, so restore-and-refresh it instead of treating
                // this as a genuine duplicate.
                if (! $this->restoreTrashedPenalty($lockedContract, $penaltyDate, $computedAmount, $meta)) {
                    return 'existing';
                }
            }

            $this->applyCreditBalance($lockedContract);

            return 'created';
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function restoreTrashedPenalty(
        Contract $contract,
        CarbonImmutable $penaltyDate,
        float $computedAmount,
        array $meta,
    ): bool {
        $trashed = Charge::query()
            ->withoutOrganizationScope()
            ->onlyTrashed()
            ->where('organization_id', $contract->organization_id)
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_PENALTY)
            ->whereDate('penalty_date', $penaltyDate->toDateString())
            ->lockForUpdate()
            ->first();

        if ($trashed === null) {
            return false;
        }

        $trashed->restore();
        $trashed->unit_id = $contract->unit_id;
        $trashed->charge_date = $penaltyDate->toDateString();
        $trashed->amount = $computedAmount;
        $trashed->meta = $meta;
        $trashed->save();

        return true;
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

    private function resolveSourceRentPeriod(
        Contract $contract,
        CarbonImmutable $cutoffDate,
        CarbonImmutable $cutoffTimestampStorage,
    ): ?string {
        $cutoffDateString = $cutoffDate->toDateString();
        $cutoffTimestampString = $cutoffTimestampStorage->format('Y-m-d H:i:s');

        $overdueRents = Charge::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $contract->organization_id)
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_RENT)
            ->whereNotNull('grace_until')
            ->whereDate('grace_until', '<=', $cutoffDateString)
            ->orderBy('period')
            ->get(['id', 'period', 'amount']);

        if ($overdueRents->isEmpty()) {
            return null;
        }

        $allocatedByChargeId = PaymentAllocation::query()
            ->withoutOrganizationScope()
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->where('payment_allocations.organization_id', $contract->organization_id)
            ->where('payments.organization_id', $contract->organization_id)
            ->whereNull('payment_allocations.deleted_at')
            ->whereNull('payments.deleted_at')
            ->whereIn('payment_allocations.charge_id', $overdueRents->pluck('id'))
            ->where('payments.paid_at', '<=', $cutoffTimestampString)
            ->groupBy('payment_allocations.charge_id')
            ->selectRaw('payment_allocations.charge_id, SUM(payment_allocations.amount) as allocated_total')
            ->pluck('allocated_total', 'charge_id');

        foreach ($overdueRents as $rent) {
            $allocated = (float) ($allocatedByChargeId[$rent->id] ?? 0);

            if (round((float) $rent->amount - $allocated, 2) <= 0) {
                continue;
            }

            $period = is_string($rent->period) ? trim($rent->period) : '';

            return $period !== '' ? $period : null;
        }

        return null;
    }

    private function hasOverdueRentBalance(
        Contract $contract,
        CarbonImmutable $cutoffDate,
        CarbonImmutable $cutoffTimestampStorage,
    ): bool {
        $cutoffDateString = $cutoffDate->toDateString();
        $cutoffTimestampString = $cutoffTimestampStorage->format('Y-m-d H:i:s');

        $totalOverdueRent = (float) Charge::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $contract->organization_id)
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_RENT)
            ->whereDate('charge_date', '<=', $cutoffDateString)
            ->whereNotNull('grace_until')
            ->whereDate('grace_until', '<=', $cutoffDateString)
            ->sum('amount');

        if ($totalOverdueRent <= 0) {
            return false;
        }

        $allocatedToOverdueRent = (float) PaymentAllocation::query()
            ->withoutOrganizationScope()
            ->join('charges', 'charges.id', '=', 'payment_allocations.charge_id')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->where('payment_allocations.organization_id', $contract->organization_id)
            ->where('charges.organization_id', $contract->organization_id)
            ->where('payments.organization_id', $contract->organization_id)
            ->whereNull('charges.deleted_at')
            ->whereNull('payment_allocations.deleted_at')
            ->whereNull('payments.deleted_at')
            ->where('charges.contract_id', $contract->id)
            ->where('charges.type', Charge::TYPE_RENT)
            ->whereDate('charges.charge_date', '<=', $cutoffDateString)
            ->whereNotNull('charges.grace_until')
            ->whereDate('charges.grace_until', '<=', $cutoffDateString)
            ->where('payments.paid_at', '<=', $cutoffTimestampString)
            ->sum('payment_allocations.amount');

        return round($totalOverdueRent - $allocatedToOverdueRent, 2) > 0;
    }

    private function calculateOverdueBalance(
        Contract $contract,
        CarbonImmutable $cutoffDate,
        CarbonImmutable $cutoffTimestampStorage,
    ): float {
        return $this->ledgerOutstandingCalculator->outstandingForContractAsOf(
            organizationId: (int) $contract->organization_id,
            contractId: (int) $contract->id,
            chargeDateTo: $cutoffDate->toDateString(),
            paymentPaidAtTo: $cutoffTimestampStorage->format('Y-m-d H:i:s'),
        );
    }

    private function toStorageTimezone(CarbonImmutable $timestamp): CarbonImmutable
    {
        return $timestamp->setTimezone($this->storageTimezone());
    }

    private function storageTimezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    private function isDuplicatePenaltyViolation(QueryException $exception): bool
    {
        if ($exception->getCode() !== '23000') {
            return false;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'charges_contract_penalty_type_unique')
            || (str_contains($message, 'penalty_date') && str_contains($message, 'unique'));
    }
}
