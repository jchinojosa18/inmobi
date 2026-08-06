<?php

namespace App\Actions\Contracts;

use App\Actions\Expenses\SeedDefaultExpenseCategoriesAction;
use App\Actions\Payments\ApplyCreditBalanceAction;
use App\Actions\Payments\ApplyDepositToOutstandingAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\Document;
use App\Models\Expense;
use App\Support\AuditLogger;
use App\Support\DepositBalanceService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ProcessContractSettlementAction
{
    public function __construct(
        private readonly DepositBalanceService $depositBalanceService,
        private readonly AuditLogger $auditLogger,
        private readonly ApplyCreditBalanceAction $applyCreditBalanceAction,
        private readonly ApplyDepositToOutstandingAction $applyDepositToOutstandingAction,
    ) {}

    /**
     * @param  list<array{description:string,amount:float|int|string,evidence?:UploadedFile|null}>  $concepts
     */
    public function execute(Contract $contract, string $moveOutDate, array $concepts, ?int $userId = null): ContractSettlementResult
    {
        $exitDate = CarbonImmutable::parse($moveOutDate, 'America/Tijuana')->startOfDay();
        $batchId = (string) Str::uuid();

        /** @var array{result: ContractSettlementResult, evidences: array<int, UploadedFile>} $transactionData */
        $transactionData = DB::transaction(function () use ($contract, $concepts, $exitDate, $batchId, $userId): array {
            $lockedContract = Contract::query()
                ->withoutOrganizationScope()
                ->with('tenant:id,full_name')
                ->lockForUpdate()
                ->findOrFail($contract->id);

            if ($lockedContract->status === Contract::STATUS_ENDED) {
                throw new RuntimeException('El contrato ya fue finiquitado.');
            }

            if ($lockedContract->status === Contract::STATUS_CANCELLED) {
                throw new RuntimeException('El contrato está anulado y no admite finiquito.');
            }

            if (data_get($lockedContract->meta, 'settlement_batch_id')) {
                throw new RuntimeException('Ya existe un finiquito registrado para este contrato.');
            }

            $this->applyCreditBalance($lockedContract);

            $moveoutTotal = 0.0;
            $moveoutChargeIds = [];
            $evidences = [];

            foreach ($concepts as $index => $concept) {
                $amount = round((float) ($concept['amount'] ?? 0), 2);
                if ($amount <= 0) {
                    continue;
                }

                $description = trim((string) ($concept['description'] ?? ''));
                if ($description === '') {
                    continue;
                }

                $moveoutCharge = Charge::query()
                    ->withoutOrganizationScope()
                    ->create([
                        'organization_id' => $lockedContract->organization_id,
                        'contract_id' => $lockedContract->id,
                        'unit_id' => $lockedContract->unit_id,
                        'type' => Charge::TYPE_MOVEOUT,
                        'period' => $exitDate->format('Y-m'),
                        'charge_date' => $exitDate->toDateString(),
                        'amount' => $amount,
                        'meta' => [
                            'subtype' => $description,
                            'settlement_batch_id' => $batchId,
                            'line_index' => $index,
                            'created_by_user_id' => $userId,
                        ],
                    ]);

                $moveoutTotal = round($moveoutTotal + $amount, 2);
                $moveoutChargeIds[] = $moveoutCharge->id;

                $evidence = $concept['evidence'] ?? null;
                if ($evidence instanceof UploadedFile) {
                    $evidences[$moveoutCharge->id] = $evidence;
                }
            }

            $outstandingBeforeDeposit = $this->depositBalanceService->outstandingBalanceExcludingDepositHold($lockedContract);
            $depositAvailable = $this->depositBalanceService->availableDepositAmount($lockedContract);

            $depositApplied = round(min($depositAvailable, $outstandingBeforeDeposit), 2);
            $depositApplyChargeId = null;
            if ($depositApplied > 0) {
                $depositApply = Charge::query()
                    ->withoutOrganizationScope()
                    ->create([
                        'organization_id' => $lockedContract->organization_id,
                        'contract_id' => $lockedContract->id,
                        'unit_id' => $lockedContract->unit_id,
                        'type' => Charge::TYPE_DEPOSIT_APPLY,
                        'period' => $exitDate->format('Y-m'),
                        'charge_date' => $exitDate->toDateString(),
                        'amount' => -$depositApplied,
                        'meta' => [
                            'subtype' => 'SETTLEMENT_APPLY',
                            'reason' => 'Aplicación automática de depósito en finiquito',
                            'settlement_batch_id' => $batchId,
                            'created_by_user_id' => $userId,
                        ],
                    ]);

                $depositApplyChargeId = $depositApply->id;

                $this->applyDepositToOutstandingAction->execute(
                    contract: $lockedContract,
                    amount: $depositApplied,
                    settlementBatchId: $batchId,
                    paidAt: $exitDate,
                );
            }

            $depositRefund = round(max($depositAvailable - $depositApplied, 0), 2);

            $creditBalance = CreditBalance::query()
                ->withoutOrganizationScope()
                ->where('organization_id', $lockedContract->organization_id)
                ->where('contract_id', $lockedContract->id)
                ->lockForUpdate()
                ->first();

            $creditRefunded = round(max((float) ($creditBalance?->balance ?? 0), 0), 2);
            if ($creditRefunded > 0 && $creditBalance !== null) {
                $depositRefund = round($depositRefund + $creditRefunded, 2);
                $creditMeta = is_array($creditBalance->meta) ? $creditBalance->meta : [];
                $creditMeta['settled_at_finiquito'] = now('America/Tijuana')->toIso8601String();
                $creditMeta['settlement_batch_id'] = $batchId;
                $creditMeta['credit_refunded'] = $creditRefunded;
                $creditBalance->balance = 0;
                $creditBalance->meta = $creditMeta;
                $creditBalance->save();
            }

            $refundExpenseId = null;
            if ($depositRefund > 0) {
                $refundCategoryId = app(SeedDefaultExpenseCategoriesAction::class)
                    ->depositRefundCategoryId($lockedContract->organization_id);

                $refundExpense = Expense::query()
                    ->withoutOrganizationScope()
                    ->create([
                        'organization_id' => $lockedContract->organization_id,
                        'unit_id' => $lockedContract->unit_id,
                        'expense_category_id' => $refundCategoryId,
                        'contract_id' => $lockedContract->id,
                        'amount' => $depositRefund,
                        'spent_at' => $exitDate->toDateString(),
                        'vendor' => $lockedContract->tenant?->full_name,
                        'notes' => $creditRefunded > 0
                            ? 'Devolución de depósito y saldo a favor por finiquito'
                            : 'Devolución de depósito por finiquito',
                        'meta' => [
                            'contract_id' => $lockedContract->id,
                            'settlement_batch_id' => $batchId,
                            'reason' => 'contract_settlement',
                            'created_by_user_id' => $userId,
                            'credit_refunded' => $creditRefunded,
                            'deposit_portion' => round(max($depositAvailable - $depositApplied, 0), 2),
                        ],
                    ]);

                $refundExpenseId = $refundExpense->id;
            }

            $balanceToCollect = round(max($outstandingBeforeDeposit - $depositApplied, 0), 2);

            $lockedContract->status = Contract::STATUS_ENDED;
            $lockedContract->ends_at = $exitDate->toDateString();

            $meta = is_array($lockedContract->meta) ? $lockedContract->meta : [];
            $meta['settlement_batch_id'] = $batchId;
            $settlements = is_array(data_get($meta, 'settlements')) ? $meta['settlements'] : [];
            $settlements[$batchId] = [
                'batch_id' => $batchId,
                'move_out_date' => $exitDate->toDateString(),
                'moveout_total' => $moveoutTotal,
                'outstanding_before_deposit' => $outstandingBeforeDeposit,
                'deposit_available' => $depositAvailable,
                'deposit_applied' => $depositApplied,
                'deposit_refund' => $depositRefund,
                'credit_refunded' => $creditRefunded,
                'balance_to_collect' => $balanceToCollect,
                'moveout_charge_ids' => $moveoutChargeIds,
                'deposit_apply_charge_id' => $depositApplyChargeId,
                'refund_expense_id' => $refundExpenseId,
                'closed_by_user_id' => $userId,
                'closed_at' => now('America/Tijuana')->toIso8601String(),
            ];
            $meta['settlements'] = $settlements;
            $lockedContract->meta = $meta;
            $lockedContract->save();

            return [
                'result' => new ContractSettlementResult(
                    batchId: $batchId,
                    moveoutTotal: $moveoutTotal,
                    outstandingBeforeDeposit: $outstandingBeforeDeposit,
                    depositAvailable: $depositAvailable,
                    depositApplied: $depositApplied,
                    depositRefund: $depositRefund,
                    balanceToCollect: $balanceToCollect,
                    depositApplyChargeId: $depositApplyChargeId,
                    refundExpenseId: $refundExpenseId,
                    moveoutChargeIds: $moveoutChargeIds,
                ),
                'evidences' => $evidences,
            ];
        }, 3);

        foreach ($transactionData['evidences'] as $chargeId => $evidence) {
            $this->storeMoveoutEvidence(
                contract: $contract,
                chargeId: (int) $chargeId,
                batchId: $batchId,
                evidence: $evidence,
            );
        }

        $result = $transactionData['result'];

        $this->auditLogger->log(
            action: 'settlement.completed',
            auditable: $contract,
            summary: sprintf(
                'Finiquito completado en contrato #%d, moveout $%s, depósito aplicado $%s',
                $contract->id,
                number_format($result->moveoutTotal, 2),
                number_format($result->depositApplied, 2),
            ),
            meta: [
                'batch_id' => $batchId,
                'contract_id' => $contract->id,
                'moveout_total' => $result->moveoutTotal,
                'deposit_applied' => $result->depositApplied,
                'deposit_refund' => $result->depositRefund,
                'balance_to_collect' => $result->balanceToCollect,
                'credit_refunded' => data_get($contract->fresh()->meta, "settlements.{$batchId}.credit_refunded", 0),
            ],
            actorUserId: $userId,
        );

        return $result;
    }

    private function applyCreditBalance(Contract $contract): void
    {
        $previousOrganizationId = TenantContext::currentOrganizationId();
        TenantContext::setOrganizationId($contract->organization_id);

        try {
            $this->applyCreditBalanceAction->execute($contract);
        } finally {
            TenantContext::setOrganizationId($previousOrganizationId);
        }
    }

    private function storeMoveoutEvidence(Contract $contract, int $chargeId, string $batchId, UploadedFile $evidence): void
    {
        $disk = (string) config('filesystems.documents_disk', 'local');
        $path = $evidence->store('documents/settlements/'.$contract->organization_id, $disk);

        Document::storeNew([
            'organization_id' => $contract->organization_id,
            'documentable_type' => Charge::class,
            'documentable_id' => $chargeId,
            'path' => $path,
            'mime' => $evidence->getMimeType() ?: 'application/octet-stream',
            'size' => $evidence->getSize() ?: 0,
            'type' => 'MOVEOUT_EVIDENCE',
            'tags' => ['settlement', 'moveout', 'evidence'],
            'meta' => [
                'disk' => $disk,
                'settlement_batch_id' => $batchId,
                'uploaded_at' => now('America/Tijuana')->toIso8601String(),
            ],
        ]);
    }
}
