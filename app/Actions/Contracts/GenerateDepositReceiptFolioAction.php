<?php

namespace App\Actions\Contracts;

use App\Models\Charge;
use Carbon\CarbonInterface;

class GenerateDepositReceiptFolioAction
{
    public function execute(int $organizationId, CarbonInterface $receivedAt): string
    {
        $year = $receivedAt->format('Y');
        $prefix = "DEP-{$year}-";
        $padding = 5;

        Charge::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->where('type', Charge::TYPE_DEPOSIT_HOLD)
            ->lockForUpdate()
            ->pluck('id');

        $latestSequence = 0;

        $metas = Charge::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->where('type', Charge::TYPE_DEPOSIT_HOLD)
            ->whereNotNull('meta')
            ->get(['meta']);

        foreach ($metas as $charge) {
            $folio = data_get($charge->meta, 'deposit_receipt_folio');
            if (! is_string($folio) || ! str_starts_with($folio, $prefix)) {
                continue;
            }

            $segment = substr($folio, strlen($prefix));
            if (ctype_digit($segment)) {
                $latestSequence = max($latestSequence, (int) $segment);
            }
        }

        return $prefix.str_pad((string) ($latestSequence + 1), $padding, '0', STR_PAD_LEFT);
    }
}
