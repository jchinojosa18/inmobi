<?php

namespace App\Actions\Contracts;

use App\Models\Document;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class GenerateDepositRefundReceiptFolioAction
{
    public function execute(int $organizationId, CarbonInterface $at): string
    {
        $year = $at->format('Y');
        $prefix = "DEV-{$year}-";
        $padding = 5;
        $folioExpression = $this->folioExpression();
        $kindExpression = $this->kindExpression();

        // Soft-deleted receipts still occupy the human folio sequence; include them.
        $latestFolio = Document::query()
            ->withoutOrganizationScope()
            ->withTrashed()
            ->where('organization_id', $organizationId)
            ->whereNotNull('meta')
            ->whereRaw("{$kindExpression} = ?", ['deposit_refund_receipt'])
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

    private function folioExpression(): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "json_extract(meta, '$.folio')";
        }

        return "JSON_UNQUOTE(JSON_EXTRACT(meta, '$.folio'))";
    }

    private function kindExpression(): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "json_extract(meta, '$.kind')";
        }

        return "JSON_UNQUOTE(JSON_EXTRACT(meta, '$.kind'))";
    }
}
