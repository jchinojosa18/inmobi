<?php

namespace App\Actions\Contracts;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Expense;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class GenerateDepositRefundReceiptPdfAction
{
    /**
     * @param  array<string, mixed>  $summary
     */
    public function execute(Contract $contract, array $summary, int $refundExpenseId, ?int $userId): Document
    {
        $contract->loadMissing(['tenant', 'unit.property']);

        $batchId = (string) ($summary['settlement_batch_id'] ?? '');

        $moveoutCharges = Charge::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $contract->organization_id)
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_MOVEOUT)
            ->when($batchId !== '', fn ($query) => $query->where('meta->settlement_batch_id', $batchId))
            ->with('documents')
            ->orderBy('id')
            ->get();

        $depositApply = Charge::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $contract->organization_id)
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_DEPOSIT_APPLY)
            ->when($batchId !== '', fn ($query) => $query->where('meta->settlement_batch_id', $batchId))
            ->first();

        $refundExpense = Expense::query()
            ->withoutOrganizationScope()
            ->with('expenseCategory')
            ->where('organization_id', $contract->organization_id)
            ->where('contract_id', $contract->id)
            ->whereKey($refundExpenseId)
            ->first();

        $pdf = Pdf::loadView('pdf.deposit-refund-receipt', [
            'contract' => $contract,
            'summary' => $summary,
            'moveoutCharges' => $moveoutCharges,
            'depositApply' => $depositApply,
            'refundExpense' => $refundExpense,
        ])->setPaper('letter', 'portrait');

        $disk = (string) config('filesystems.documents_disk', 'local');
        $folder = 'documents/contract/'.$contract->organization_id;
        $filename = 'deposit-refund-'.$contract->id.'-'.now('America/Tijuana')->format('YmdHis').'.pdf';
        $path = $folder.'/'.$filename;

        Storage::disk($disk)->put($path, $pdf->output());

        $generatedAt = now('America/Tijuana');

        return Document::storeNew([
            'organization_id' => (int) $contract->organization_id,
            'documentable_type' => Contract::class,
            'documentable_id' => $contract->id,
            'path' => $path,
            'mime' => 'application/pdf',
            'size' => Storage::disk($disk)->size($path),
            'type' => 'CONTRACT_DOCUMENT',
            'category' => null,
            'tags' => ['deposit_refund', 'generated'],
            'meta' => [
                'disk' => $disk,
                'generated' => true,
                'kind' => 'deposit_refund_receipt',
                'folio' => $summary['folio'],
                'settlement_batch_id' => $summary['settlement_batch_id'],
                'refund_expense_id' => $refundExpenseId,
                'generated_at' => $generatedAt->toIso8601String(),
                'generated_by_user_id' => $userId,
            ],
        ]);
    }
}
