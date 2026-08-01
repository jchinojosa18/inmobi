<?php

namespace App\Actions\Contracts;

use App\Models\Contract;
use App\Models\Document;
use App\Support\ContractDocumentCategory;
use App\Support\DateDisplay;
use App\Support\MoneyToWords;
use App\Support\OrganizationSettingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GenerateLeaseAgreementPdfAction
{
    public function __construct(
        private readonly OrganizationSettingsService $organizationSettingsService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function viewData(Contract $contract, ?CarbonImmutable $documentDate = null): array
    {
        $contract->loadMissing(['tenant', 'unit.property']);

        $settings = $this->organizationSettingsService->forOrganization((int) $contract->organization_id);
        $landlordName = is_string($settings['landlord_name'] ?? null) ? trim($settings['landlord_name']) : '';

        if ($landlordName === '') {
            throw ValidationException::withMessages([
                'landlord_name' => 'Configure el nombre del arrendador en Configuración antes de generar el contrato.',
            ]);
        }

        $documentDate ??= CarbonImmutable::now('America/Tijuana');
        $startsAt = CarbonImmutable::parse($contract->starts_at, 'America/Tijuana')->startOfDay();
        $endsAt = CarbonImmutable::parse($contract->ends_at, 'America/Tijuana')->startOfDay();

        $property = $contract->unit?->property;
        $addressParts = array_filter([
            $property?->address,
            $contract->unit?->name,
        ]);

        return [
            'contract' => $contract,
            'document_date' => $documentDate,
            'document_day' => $documentDate->day,
            'document_month' => mb_strtolower($documentDate->locale('es')->translatedFormat('F'), 'UTF-8'),
            'document_year' => $documentDate->year,
            'landlord_name' => $landlordName,
            'landlord_rep' => is_string($settings['landlord_rep'] ?? null) ? trim($settings['landlord_rep']) : null,
            'tenant_name' => (string) $contract->tenant?->full_name,
            'tenant_ine' => (string) ($contract->tenant?->ine_clave ?? ''),
            'property_address' => implode(', ', $addressParts),
            'starts_at' => DateDisplay::formatDate($startsAt),
            'ends_at' => DateDisplay::formatDate($endsAt),
            'term_description' => $this->termDescription($startsAt, $endsAt),
            'rent_amount' => (float) $contract->rent_amount,
            'rent_amount_formatted' => number_format((float) $contract->rent_amount, 2),
            'rent_amount_words' => MoneyToWords::mxn((float) $contract->rent_amount),
            'due_day' => (int) $contract->due_day,
            'deposit_amount' => (float) $contract->deposit_amount,
            'deposit_amount_formatted' => number_format((float) $contract->deposit_amount, 2),
            'deposit_amount_words' => MoneyToWords::mxn((float) $contract->deposit_amount),
        ];
    }

    public function execute(Contract $contract, ?int $userId): Document
    {
        $data = $this->viewData($contract);

        $pdf = Pdf::loadView('pdf.lease-agreement', $data)
            ->setPaper('letter', 'portrait');

        $disk = (string) config('filesystems.documents_disk', 'local');
        $folder = 'documents/contract/'.$contract->organization_id;
        $filename = 'lease-agreement-'.$contract->id.'-'.now('America/Tijuana')->format('YmdHis').'.pdf';
        $path = $folder.'/'.$filename;

        Storage::disk($disk)->put($path, $pdf->output());

        return Document::query()
            ->withoutOrganizationScope()
            ->create([
                'organization_id' => (int) $contract->organization_id,
                'documentable_type' => Contract::class,
                'documentable_id' => $contract->id,
                'path' => $path,
                'mime' => 'application/pdf',
                'size' => Storage::disk($disk)->size($path),
                'type' => 'CONTRACT_DOCUMENT',
                'category' => ContractDocumentCategory::Contract,
                'tags' => ['contract', 'generated', 'lease_agreement'],
                'meta' => [
                    'disk' => $disk,
                    'generated' => true,
                    'kind' => 'lease_agreement',
                    'generated_at' => now('America/Tijuana')->toIso8601String(),
                    'generated_by_user_id' => $userId,
                ],
            ]);
    }

    private function termDescription(CarbonImmutable $startsAt, CarbonImmutable $endsAt): string
    {
        $months = max($startsAt->diffInMonths($endsAt), 1);

        return $months.' meses';
    }
}
