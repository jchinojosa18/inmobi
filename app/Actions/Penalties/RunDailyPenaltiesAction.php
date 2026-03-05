<?php

namespace App\Actions\Penalties;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class RunDailyPenaltiesAction
{
    private const TIMEZONE = 'America/Tijuana';

    private const ALGORITHM_VERSION = 'v1_compound_daily';

    /**
     * @var list<string>
     */
    private const EXCLUDED_TYPES = [
        Charge::TYPE_DEPOSIT_HOLD,
        Charge::TYPE_DEPOSIT_APPLY,
        'DEPOSIT',
        'SECURITY_DEPOSIT',
    ];

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
                        'meta' => [
                            'base_amount' => $baseAmount,
                            'rate_daily' => $rateDaily,
                            'computed_amount' => $computedAmount,
                            'algorithm_version' => self::ALGORITHM_VERSION,
                            'cutoff_timestamp' => $cutoffTimestampLocal->toIso8601String(),
                            'cutoff_timestamp_storage' => $cutoffTimestampStorage->toIso8601String(),
                        ],
                    ]);
            } catch (QueryException $exception) {
                if ($this->isDuplicatePenaltyViolation($exception)) {
                    return 'existing';
                }

                throw $exception;
            }

            return 'created';
        }, 3);
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
        $cutoffDateString = $cutoffDate->toDateString();
        $cutoffTimestampString = $cutoffTimestampStorage->format('Y-m-d H:i:s');

        $totalCharges = (float) Charge::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $contract->organization_id)
            ->where('contract_id', $contract->id)
            ->whereDate('charge_date', '<=', $cutoffDateString)
            ->whereNotIn('type', self::EXCLUDED_TYPES)
            ->sum('amount');

        $allocatedAmount = (float) PaymentAllocation::query()
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
            ->whereDate('charges.charge_date', '<=', $cutoffDateString)
            ->whereNotIn('charges.type', self::EXCLUDED_TYPES)
            ->where('payments.paid_at', '<=', $cutoffTimestampString)
            ->sum('payment_allocations.amount');

        $creditAsOfCutoff = $this->resolveCreditAsOfCutoff($contract, $cutoffTimestampStorage);

        return round(max($totalCharges - $allocatedAmount - $creditAsOfCutoff, 0), 2);
    }

    private function resolveCreditAsOfCutoff(Contract $contract, CarbonImmutable $cutoffTimestampStorage): float
    {
        $creditedFromPayments = Payment::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $contract->organization_id)
            ->where('contract_id', $contract->id)
            ->where('paid_at', '<=', $cutoffTimestampStorage->format('Y-m-d H:i:s'))
            ->get(['meta'])
            ->reduce(function (float $carry, Payment $payment): float {
                return $carry + (float) data_get($payment->meta, 'credited_amount', 0);
            }, 0.0);

        $creditBalance = CreditBalance::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $contract->organization_id)
            ->where('contract_id', $contract->id)
            ->first();

        if ($creditBalance === null || $creditBalance->last_payment_id === null) {
            return round(max($creditedFromPayments, 0), 2);
        }

        $lastPaymentAt = Payment::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $contract->organization_id)
            ->where('id', $creditBalance->last_payment_id)
            ->value('paid_at');

        if (! is_string($lastPaymentAt) || $lastPaymentAt === '') {
            return round(max($creditedFromPayments, 0), 2);
        }

        $lastPaymentTimestamp = CarbonImmutable::parse($lastPaymentAt, $this->storageTimezone());
        if ($lastPaymentTimestamp->gt($cutoffTimestampStorage)) {
            return round(max($creditedFromPayments, 0), 2);
        }

        return round(max($creditedFromPayments, (float) $creditBalance->balance, 0), 2);
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
            || str_contains($message, 'contract_id')
            || str_contains($message, 'unique');
    }
}
