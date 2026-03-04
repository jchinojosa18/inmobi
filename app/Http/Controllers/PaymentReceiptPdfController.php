<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Support\PaymentReceiptDataBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentReceiptPdfController extends Controller
{
    public function __invoke(Request $request, int $paymentId, PaymentReceiptDataBuilder $builder): Response
    {
        $payment = Payment::query()
            ->withoutOrganizationScope()
            ->findOrFail($paymentId);

        if ($request->user() !== null && $request->user()->organization_id !== $payment->organization_id) {
            abort(403);
        }

        $receipt = $builder->build($payment);

        return Pdf::loadView('pdf.payment-receipt', ['receipt' => $receipt])
            ->setPaper('letter', 'portrait')
            ->stream('receipt-'.$payment->receipt_folio.'.pdf');
    }
}
