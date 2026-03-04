<?php

namespace App\Actions\MonthCloses;

use App\Models\MonthClose;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloseMonthAction
{
    public function __construct(
        private readonly BuildMonthCloseSnapshotAction $snapshotAction,
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

            return MonthClose::query()
                ->withoutOrganizationScope()
                ->create([
                    'organization_id' => $organizationId,
                    'month' => $month,
                    'closed_at' => CarbonImmutable::now('America/Tijuana')->toDateTimeString(),
                    'closed_by_user_id' => $userId,
                    'snapshot' => $snapshot,
                    'notes' => $notes,
                ]);
        }, 3);
    }
}
