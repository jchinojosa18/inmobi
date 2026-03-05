<?php

namespace App\Actions\MonthCloses;

use App\Models\MonthClose;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

class ReopenMonthAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(int $organizationId, string $month): bool
    {
        $result = DB::transaction(function () use ($organizationId, $month): bool {
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

        if ($result) {
            $this->auditLogger->log(
                action: 'month.reopened',
                auditable: null,
                summary: "Mes reabierto: {$month}",
                meta: [
                    'month' => $month,
                    'organization_id' => $organizationId,
                ],
                organizationId: $organizationId,
            );
        }

        return $result;
    }
}
