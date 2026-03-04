<?php

namespace App\Actions\Payments;

use App\Models\OrganizationSetting;
use App\Models\Payment;
use App\Support\OrganizationSettingsService;
use Carbon\CarbonInterface;

class GenerateReceiptFolioAction
{
    public function __construct(
        private readonly OrganizationSettingsService $settingsService,
    ) {}

    public function execute(int $organizationId, CarbonInterface $paidAt): string
    {
        $year = $paidAt->format('Y');
        $settings = $this->settingsService->forOrganization($organizationId);
        $mode = (string) $settings['receipt_folio_mode'];
        $prefix = trim((string) $settings['receipt_folio_prefix']);
        $padding = (int) $settings['receipt_folio_padding'];

        $folioPrefix = $this->folioPrefix(
            mode: $mode,
            prefix: $prefix,
            year: $year
        );

        $latestFolio = Payment::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->where('receipt_folio', 'like', $folioPrefix.'%')
            ->lockForUpdate()
            ->max('receipt_folio');

        $nextSequence = $this->nextSequence($latestFolio, $padding);

        return $folioPrefix.str_pad((string) $nextSequence, $padding, '0', STR_PAD_LEFT);
    }

    private function folioPrefix(string $mode, string $prefix, string $year): string
    {
        if ($mode === OrganizationSetting::RECEIPT_MODE_CONTINUOUS) {
            return "{$prefix}-";
        }

        return "{$prefix}-{$year}-";
    }

    private function nextSequence(?string $latestFolio, int $padding): int
    {
        if (! is_string($latestFolio)) {
            return 1;
        }

        $lastSegment = substr($latestFolio, -$padding);

        if (! ctype_digit($lastSegment)) {
            return 1;
        }

        return ((int) $lastSegment) + 1;
    }
}
