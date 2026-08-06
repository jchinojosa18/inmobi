<?php

namespace App\Actions\Contracts;

use App\Models\Contract;
use App\Models\Document;
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

        $pdf = Pdf::loadView('pdf.deposit-refund-receipt', [
            'contract' => $contract,
            'summary' => $summary,
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
