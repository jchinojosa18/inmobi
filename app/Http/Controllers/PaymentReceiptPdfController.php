<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Support\PaymentReceiptDataBuilder;
use App\Support\StreamsPdfResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentReceiptPdfController extends Controller
{
    use StreamsPdfResponse;

    public function __invoke(Request $request, int $paymentId, PaymentReceiptDataBuilder $builder): Response
    {
        $payment = Payment::query()
            ->withoutOrganizationScope()
            ->findOrFail($paymentId);

        // Authenticated PDF download: block cross-tenant access.
        // Shareable signed links authorize by signature alone (tenants / WhatsApp / other sessions).
        if (
            $request->routeIs('payments.receipt.pdf')
            && $request->user() !== null
            && (int) $request->user()->organization_id !== (int) $payment->organization_id
        ) {
            abort(403);
        }

        if ($payment->receipt_folio === null) {
            abort(404);
        }

        $receipt = $builder->build($payment);

        $pdf = Pdf::loadView('pdf.payment-receipt', ['receipt' => $receipt])
            ->setPaper('letter', 'portrait');

        return $this->streamPdf($pdf, $request, 'receipt-'.$payment->receipt_folio.'.pdf');
    }
}
