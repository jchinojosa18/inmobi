<?php

namespace App\Mail;

use App\Models\Payment;
use App\Support\OrganizationSettingsService;
use App\Support\PaymentReceiptDataBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class PaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Payment $payment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recibo de pago '.$this->payment->receipt_folio,
        );
    }

    public function content(): Content
    {
        $payment = $this->payment->fresh();
        $receipt = app(PaymentReceiptDataBuilder::class)->build($payment);
        $shareUrl = URL::temporarySignedRoute(
            'payments.receipt.share',
            now()->addDays(7),
            ['paymentId' => $payment->id]
        );
        $settingsService = app(OrganizationSettingsService::class);
        $settings = $settingsService->forOrganization((int) $payment->organization_id);
        $unitName = trim((string) ($payment->contract?->unit?->property?->name.' / '.$payment->contract?->unit?->name));
        $messageBody = $settingsService->renderTemplate(
            (string) $settings['email_template'],
            [
                'tenant_name' => (string) ($payment->contract?->tenant?->full_name ?? 'cliente'),
                'unit_name' => $unitName !== '' ? $unitName : 'unidad',
                'amount_due' => number_format((float) $payment->amount, 2, '.', ''),
                'shared_receipt_url' => $shareUrl,
            ]
        );

        return new Content(
            view: 'emails.payment-receipt',
            with: [
                'receipt' => $receipt,
                'shareUrl' => $shareUrl,
                'messageBody' => $messageBody,
            ],
        );
    }

    public function build(): self
    {
        $payment = $this->payment->fresh();
        $receipt = app(PaymentReceiptDataBuilder::class)->build($payment);

        $pdfContent = Pdf::loadView('pdf.payment-receipt', [
            'receipt' => $receipt,
        ])->output();

        return $this->attachData(
            $pdfContent,
            'receipt-'.$payment->receipt_folio.'.pdf',
            ['mime' => 'application/pdf']
        );
    }
}
