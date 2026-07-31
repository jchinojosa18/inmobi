<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Expense;
use App\Support\StreamsPdfResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ContractSettlementPdfController extends Controller
{
    use StreamsPdfResponse;

    public function __invoke(Request $request, Contract $contract, string $batch): Response
    {
        $contract = Contract::query()
            ->with(['tenant', 'unit.property'])
            ->findOrFail($contract->id);

        $moveoutCharges = Charge::query()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_MOVEOUT)
            ->where('meta->settlement_batch_id', $batch)
            ->with('documents')
            ->orderBy('id')
            ->get();

        $depositApply = Charge::query()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_DEPOSIT_APPLY)
            ->where('meta->settlement_batch_id', $batch)
            ->first();

        $refundExpense = Expense::query()
            ->with('expenseCategory')
            ->where('organization_id', $contract->organization_id)
            ->where('contract_id', $contract->id)
            ->where('meta->settlement_batch_id', $batch)
            ->whereHas('expenseCategory', fn ($query) => $query
                ->where('is_system', true)
                ->where('name', 'REEMBOLSO DEPÓSITO'))
            ->first();

        $summary = data_get($contract->meta, "settlements.{$batch}", []);

        abort_if($moveoutCharges->isEmpty() && empty($summary), 404);

        $pdf = Pdf::loadView('pdf.contract-settlement', [
            'contract' => $contract,
            'batch' => $batch,
            'summary' => is_array($summary) ? $summary : [],
            'moveoutCharges' => $moveoutCharges,
            'depositApply' => $depositApply,
            'refundExpense' => $refundExpense,
        ])->setPaper('letter', 'portrait');

        return $this->streamPdf($pdf, $request, 'finiquito-'.$contract->id.'-'.$batch.'.pdf');
    }
}
