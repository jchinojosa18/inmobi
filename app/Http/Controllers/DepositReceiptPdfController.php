<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Support\DepositReceiptDataBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DepositReceiptPdfController extends Controller
{
    public function __invoke(Request $request, int $chargeId, DepositReceiptDataBuilder $builder): Response
    {
        $charge = Charge::query()
            ->withoutOrganizationScope()
            ->findOrFail($chargeId);

        if ($charge->type !== Charge::TYPE_DEPOSIT_HOLD) {
            abort(404);
        }

        if ($request->user() !== null && $request->user()->organization_id !== $charge->organization_id) {
            abort(403);
        }

        $folio = data_get($charge->meta, 'deposit_receipt_folio');
        if (! is_string($folio) || $folio === '') {
            abort(404);
        }

        $receipt = $builder->build($charge);

        return Pdf::loadView('pdf.deposit-receipt', ['receipt' => $receipt])
            ->setPaper('letter', 'portrait')
            ->stream('deposit-receipt-'.$folio.'.pdf');
    }
}
