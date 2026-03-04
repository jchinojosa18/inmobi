<?php

namespace App\Actions\MonthCloses;

use App\Models\MonthClose;
use Illuminate\Support\Facades\DB;

class ReopenMonthAction
{
    public function execute(int $organizationId, string $month): bool
    {
        return DB::transaction(function () use ($organizationId, $month): bool {
            $monthClose = MonthClose::query()
                ->withoutOrganizationScope()
                ->where('organization_id', $organizationId)
                ->where('month', $month)
                ->lockForUpdate()
                ->first();

            if ($monthClose === null) {
                return false;
            }

            $monthClose->forceDelete();

            return true;
        }, 3);
    }
}
