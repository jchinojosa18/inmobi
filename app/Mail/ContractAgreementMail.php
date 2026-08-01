<?php

namespace App\Mail;

use App\Actions\Contracts\GenerateLeaseAgreementPdfAction;
use App\Models\Contract;
use App\Support\ContractAgreementShareUrl;
use App\Support\DateDisplay;
use App\Support\OrganizationMailSender;
use App\Support\OrganizationSettingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContractAgreementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Contract $contract) {}

    public function envelope(): Envelope
    {
        $contract = $this->contract->fresh(['unit.property', 'organization']);
        $organizationName = (string) ($contract->organization?->name ?? '');
        $unitName = trim((string) ($contract->unit?->property?->name.' / '.$contract->unit?->name));

        return new Envelope(
            from: OrganizationMailSender::fromAddress($organizationName),
            subject: 'Contrato de arrendamiento'.($unitName !== '' ? ' — '.$unitName : ''),
        );
    }

    public function content(): Content
    {
        $contract = $this->contract->fresh(['tenant', 'unit.property', 'organization']);
        $organizationName = (string) ($contract->organization?->name ?? '');
        $shareUrl = ContractAgreementShareUrl::make($contract->id);
        $settingsService = app(OrganizationSettingsService::class);
        $settings = $settingsService->forOrganization((int) $contract->organization_id);
        $unitName = trim((string) ($contract->unit?->property?->name.' / '.$contract->unit?->name));
        $messageBody = $settingsService->renderTemplate(
            (string) $settings['contract_email_template'],
            [
                'tenant_name' => (string) ($contract->tenant?->full_name ?? 'cliente'),
                'unit_name' => $unitName !== '' ? $unitName : 'unidad',
                'shared_contract_url' => $shareUrl,
                'rent_amount' => number_format((float) $contract->rent_amount, 2, '.', ''),
                'starts_at' => DateDisplay::formatDate($contract->starts_at),
                'ends_at' => DateDisplay::formatDate($contract->ends_at),
            ]
        );

        return new Content(
            view: 'emails.contract-agreement',
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
        $contract = $this->contract->fresh();
        $pdfData = app(GenerateLeaseAgreementPdfAction::class)->viewData($contract);

        $pdfContent = Pdf::loadView('pdf.lease-agreement', $pdfData)->output();

        return $this->attachData(
            $pdfContent,
            'contrato-arrendamiento-'.$contract->id.'.pdf',
            ['mime' => 'application/pdf']
        );
    }
}
