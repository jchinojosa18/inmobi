<?php

namespace App\Actions\MonthCloses;

use App\Models\MonthClose;
use App\Support\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloseMonthAction
{
    public function __construct(
        private readonly BuildMonthCloseSnapshotAction $snapshotAction,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(int $organizationId, int $userId, string $month, ?string $notes = null): MonthClose
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw ValidationException::withMessages([
                'month' => 'El mes debe tener formato YYYY-MM.',
            ]);
        }

        return DB::transaction(function () use ($organizationId, $userId, $month, $notes): MonthClose {
            $existingClose = MonthClose::query()
                ->withoutOrganizationScope()
                ->where('organization_id', $organizationId)
                ->where('month', $month)
                ->lockForUpdate()
                ->first();

            if ($existingClose !== null) {
                return $existingClose;
            }

            $snapshot = $this->snapshotAction->execute($organizationId, $month);

            try {
                $monthClose = MonthClose::query()
                    ->withoutOrganizationScope()
                    ->create([
                        'organization_id' => $organizationId,
                        'month' => $month,
                        'closed_at' => CarbonImmutable::now('America/Tijuana')->toDateTimeString(),
                        'closed_by_user_id' => $userId,
                        'snapshot' => $snapshot,
                        'notes' => $notes,
                    ]);
            } catch (QueryException $exception) {
                if (! $this->isDuplicateMonthCloseViolation($exception)) {
                    throw $exception;
                }

                $monthClose = MonthClose::query()
                    ->withoutOrganizationScope()
                    ->where('organization_id', $organizationId)
                    ->where('month', $month)
                    ->first();

                if ($monthClose === null) {
                    throw $exception;
                }

                return $monthClose;
            }

            $this->auditLogger->log(
                action: 'month.closed',
                auditable: $monthClose,
                summary: "Mes cerrado: {$month}",
                meta: [
                    'month' => $month,
                    'organization_id' => $organizationId,
                    'notes' => $notes,
                ],
                organizationId: $organizationId,
                actorUserId: $userId,
            );

            return $monthClose;
        }, 3);
    }

    private function isDuplicateMonthCloseViolation(QueryException $exception): bool
    {
        if ($exception->getCode() !== '23000') {
            return false;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'month_closes_organization_id_month_unique')
            || (str_contains($message, 'organization_id') && str_contains($message, 'month') && str_contains($message, 'unique'));
    }
}
