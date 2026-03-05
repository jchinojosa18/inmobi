<?php

namespace App\Actions\Payments;

use App\Models\Contract;
use App\Models\Document;
use App\Models\Payment;
use App\Support\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RegisterContractPaymentAction
{
    public function __construct(
        private readonly GenerateReceiptFolioAction $folioAction,
        private readonly ApplyPaymentAction $applyPaymentAction,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param array{
     *     amount:numeric-string|int|float,
     *     method:string,
     *     paid_at:string,
     *     reference?:string|null
     * } $data
     */
    public function execute(Contract $contract, array $data, ?UploadedFile $evidence = null): Payment
    {
        $payment = $this->createPaymentWithRetry($contract, $data);

        $this->applyPaymentAction->execute($contract, $payment);

        if ($evidence !== null) {
            $this->storeEvidence($payment, $evidence);
        }

        $fresh = $payment->fresh(['allocations.charge', 'documents', 'contract.tenant', 'contract.unit.property']);

        $this->auditLogger->log(
            action: 'payment.created',
            auditable: $fresh,
            summary: sprintf('Pago registrado %s $%s', $fresh->receipt_folio, number_format((float) $fresh->amount, 2)),
            meta: [
                'receipt_folio' => $fresh->receipt_folio,
                'amount' => (float) $fresh->amount,
                'method' => $fresh->method,
                'contract_id' => $fresh->contract_id,
                'paid_at' => $fresh->paid_at?->toDateString(),
            ],
        );

        return $fresh;
    }

    /**
     * @param array{
     *     amount:numeric-string|int|float,
     *     method:string,
     *     paid_at:string,
     *     reference?:string|null
     * } $data
     */
    private function createPaymentWithRetry(Contract $contract, array $data): Payment
    {
        $paidAt = CarbonImmutable::parse($data['paid_at']);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                /** @var Payment $payment */
                $payment = DB::transaction(function () use ($contract, $data, $paidAt): Payment {
                    $contract = Contract::query()
                        ->withoutOrganizationScope()
                        ->lockForUpdate()
                        ->findOrFail($contract->id);

                    $folio = $this->folioAction->execute($contract->organization_id, $paidAt);

                    return Payment::query()->create([
                        'organization_id' => $contract->organization_id,
                        'contract_id' => $contract->id,
                        'paid_at' => $paidAt->toDateTimeString(),
                        'amount' => $data['amount'],
                        'method' => $data['method'],
                        'reference' => $data['reference'] ?? null,
                        'receipt_folio' => $folio,
                        'meta' => [],
                    ]);
                }, 3);

                return $payment;
            } catch (QueryException $exception) {
                if ($attempt < 3 && $this->isDuplicateFolioViolation($exception)) {
                    continue;
                }

                throw $exception;
            }
        }

        throw new RuntimeException('Unable to generate a unique receipt folio.');
    }

    private function storeEvidence(Payment $payment, UploadedFile $evidence): void
    {
        $disk = (string) config('filesystems.documents_disk', 'public');
        $path = $evidence->store('documents/payments/'.$payment->organization_id, $disk);

        Document::query()->create([
            'organization_id' => $payment->organization_id,
            'documentable_id' => $payment->id,
            'documentable_type' => Payment::class,
            'path' => $path,
            'mime' => $evidence->getMimeType() ?: 'application/octet-stream',
            'size' => $evidence->getSize() ?: 0,
            'type' => 'PAYMENT_EVIDENCE',
            'tags' => ['payment', 'evidence'],
            'meta' => [
                'disk' => $disk,
                'uploaded_at' => now()->toISOString(),
            ],
        ]);
    }

    private function isDuplicateFolioViolation(QueryException $exception): bool
    {
        return $exception->getCode() === '23000'
            && str_contains(strtolower($exception->getMessage()), 'receipt_folio');
    }
}
