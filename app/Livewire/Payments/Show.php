<?php

namespace App\Livewire\Payments;

use App\Mail\PaymentReceiptMail;
use App\Models\Payment;
use App\Support\OrganizationSettingsService;
use App\Support\PaymentReceiptDataBuilder;
use App\Support\PaymentReceiptShareUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class Show extends Component
{
    public Payment $payment;

    public function mount(Payment $payment): void
    {
        $this->payment = $payment;
    }

    public function sendEmail(): void
    {
        $recipient = $this->payment->contract?->tenant?->email;

        if (! is_string($recipient) || trim($recipient) === '') {
            $this->addError('emailRecipient', __('finance.validation.email_unavailable'));

            return;
        }

        Mail::to($recipient)->send(new PaymentReceiptMail($this->payment));

        session()->flash('success', __('finance.flash.receipt_sent'));
    }

    public function render(
        PaymentReceiptDataBuilder $builder,
        OrganizationSettingsService $settingsService
    ): View {
        $payment = Payment::query()
            ->with(['contract.unit.property', 'contract.tenant', 'allocations.charge', 'documents'])
            ->findOrFail($this->payment->id);

        $receipt = $builder->build($payment);
        $receiptUrl = route('payments.receipt.pdf', ['paymentId' => $payment->id]);
        $shareUrl = PaymentReceiptShareUrl::make($payment->id);

        $settings = $settingsService->forOrganization((int) $payment->organization_id);
        $unitName = trim((string) ($payment->contract?->unit?->property?->name.' / '.$payment->contract?->unit?->name));
        $whatsAppMessage = $settingsService->renderTemplate(
            (string) $settings['whatsapp_template'],
            [
                'tenant_name' => (string) ($payment->contract?->tenant?->full_name ?? 'cliente'),
                'unit_name' => $unitName !== '' ? $unitName : 'unidad',
                'amount_due' => number_format((float) $payment->amount, 2, '.', ''),
                'shared_receipt_url' => $shareUrl,
            ]
        );

        $whatsAppUrl = $this->buildWhatsAppUrl(
            phone: $payment->contract?->tenant?->phone,
            message: $whatsAppMessage
        );

        $documents = $payment->documents->map(function ($document) {
            return [
                'id' => $document->id,
                'path' => $document->path,
                'url' => route('documents.download', $document),
            ];
        });

        return view('livewire.payments.show', [
            'payment' => $payment,
            'receipt' => $receipt,
            'receiptUrl' => $receiptUrl,
            'whatsAppUrl' => $whatsAppUrl,
            'shareUrl' => $shareUrl,
            'documents' => $documents,
        ])->layout('layouts.app', ['title' => __('finance.payments.show_page_title')]);
    }

    private function buildWhatsAppUrl(?string $phone, string $message): string
    {
        $normalizedPhone = preg_replace('/\D+/', '', (string) $phone) ?: null;
        $encodedMessage = urlencode($message);

        if ($normalizedPhone !== null) {
            return "https://wa.me/{$normalizedPhone}?text={$encodedMessage}";
        }

        return "https://wa.me/?text={$encodedMessage}";
    }
}
