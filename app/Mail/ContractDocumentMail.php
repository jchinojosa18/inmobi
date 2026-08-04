<?php

namespace App\Mail;

use App\Models\Contract;
use App\Models\Document;
use App\Support\ContractDocumentCategory;
use App\Support\DateDisplay;
use App\Support\DocumentShareUrl;
use App\Support\OrganizationMailSender;
use App\Support\OrganizationSettingsService;
use App\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ContractDocumentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Document $document) {}

    public function envelope(): Envelope
    {
        [$contract, $organizationName, $unitName] = $this->resolveContext();

        return new Envelope(
            from: OrganizationMailSender::fromAddress($organizationName),
            subject: 'Contrato de arrendamiento'.($unitName !== '' ? ' — '.$unitName : ''),
        );
    }

    public function content(): Content
    {
        [$contract, $organizationName, $unitName] = $this->resolveContext();
        $shareUrl = DocumentShareUrl::make((int) $this->document->id);
        $settingsService = app(OrganizationSettingsService::class);
        $settings = $settingsService->forOrganization((int) $this->document->organization_id);

        $messageBody = $settingsService->renderTemplate(
            (string) $settings['contract_email_template'],
            [
                'tenant_name' => (string) ($contract->tenant?->full_name ?? 'cliente'),
                'unit_name' => $unitName !== '' ? $unitName : 'unidad',
                'shared_contract_url' => $shareUrl,
                'rent_amount' => (float) $contract->rent_amount,
                'starts_at' => DateDisplay::formatDate($contract->starts_at),
                'ends_at' => DateDisplay::formatDate($contract->ends_at),
            ]
        );

        return new Content(
            view: 'emails.contract-document',
            with: [
                'organizationName' => $organizationName,
                'contract' => $contract,
                'shareUrl' => $shareUrl,
                'messageBody' => $messageBody,
            ],
        );
    }

    public function build(): self
    {
        $document = Document::query()
            ->withoutOrganizationScope()
            ->findOrFail($this->document->id);

        if (
            $document->documentable_type !== Contract::class
            || $document->category !== ContractDocumentCategory::Contract
        ) {
            throw ValidationException::withMessages([
                'document' => 'Documento de contrato inválido.',
            ]);
        }

        $disk = (string) data_get($document->meta, 'disk', config('filesystems.documents_disk', 'local'));
        if (! Storage::disk($disk)->exists($document->path)) {
            throw ValidationException::withMessages([
                'document' => 'Archivo de contrato no encontrado.',
            ]);
        }

        $pdfContent = Storage::disk($disk)->get($document->path);

        return $this->attachData(
            $pdfContent,
            'contrato-'.$document->id.'.pdf',
            ['mime' => $document->mime ?: 'application/pdf']
        );
    }

    /**
     * @return array{0: Contract, 1: string, 2: string}
     */
    private function resolveContext(): array
    {
        $previous = TenantContext::currentOrganizationId();
        TenantContext::setOrganizationId((int) $this->document->organization_id);

        try {
            $document = Document::query()
                ->withoutOrganizationScope()
                ->findOrFail($this->document->id);

            /** @var Contract $contract */
            $contract = Contract::query()
                ->withoutOrganizationScope()
                ->with(['tenant', 'unit.property', 'organization'])
                ->findOrFail($document->documentable_id);

            $organizationName = (string) ($contract->organization?->name ?? '');
            $unitName = trim((string) ($contract->unit?->property?->name.' / '.$contract->unit?->name));

            return [$contract, $organizationName, $unitName];
        } finally {
            TenantContext::setOrganizationId($previous);
        }
    }
}
