<?php

namespace App\Http\Controllers;

use App\Actions\Contracts\GenerateLeaseAgreementPdfAction;
use App\Models\Contract;
use App\Support\StreamsPdfResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ContractAgreementPdfController extends Controller
{
    use StreamsPdfResponse;

    public function __invoke(Request $request, int $contractId, GenerateLeaseAgreementPdfAction $action): Response
    {
        $contract = Contract::query()
            ->withoutOrganizationScope()
            ->findOrFail($contractId);

        if (
            $request->routeIs('contracts.agreement.pdf')
            && $request->user() !== null
            && (int) $request->user()->organization_id !== (int) $contract->organization_id
        ) {
            abort(403);
        }

        $data = $action->viewData($contract);

        $pdf = Pdf::loadView('pdf.lease-agreement', $data)
            ->setPaper('letter', 'portrait');

        return $this->streamPdf($pdf, $request, 'contrato-arrendamiento-'.$contract->id.'.pdf');
    }
}
