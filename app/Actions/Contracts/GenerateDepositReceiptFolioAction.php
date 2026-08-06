<?php

namespace App\Actions\Contracts;

use App\Models\Charge;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class GenerateDepositReceiptFolioAction
{
    public function execute(int $organizationId, CarbonInterface $receivedAt): string
    {
        $year = $receivedAt->format('Y');
        $prefix = "DEP-{$year}-";
        $padding = 5;
        $folioExpression = $this->depositReceiptFolioExpression();

        // Soft-deleted holds still occupy the human folio sequence; include them.
        $latestFolio = Charge::query()
            ->withoutOrganizationScope()
            ->withTrashed()
            ->where('organization_id', $organizationId)
            ->where('type', Charge::TYPE_DEPOSIT_HOLD)
            ->whereNotNull('meta')
            ->whereRaw("{$folioExpression} LIKE ?", [$prefix.'%'])
            ->lockForUpdate()
            ->selectRaw("MAX({$folioExpression}) as latest_folio")
            ->value('latest_folio');

        $latestSequence = 0;
        if (is_string($latestFolio) && str_starts_with($latestFolio, $prefix)) {
            $segment = substr($latestFolio, strlen($prefix));
            if (ctype_digit($segment)) {
                $latestSequence = (int) $segment;
            }
        }

        return $prefix.str_pad((string) ($latestSequence + 1), $padding, '0', STR_PAD_LEFT);
    }

    private function depositReceiptFolioExpression(): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "json_extract(meta, '$.deposit_receipt_folio')";
        }

        return "JSON_UNQUOTE(JSON_EXTRACT(meta, '$.deposit_receipt_folio'))";
    }
}
