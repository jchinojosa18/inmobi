<?php

namespace App\Livewire\Contracts;

use App\Actions\Contracts\ProcessContractSettlementAction;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\Document;
use App\Support\DepositBalanceService;
use App\Support\FileViewerItem;
use App\Support\LedgerOutstandingCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

class SettlementWizard extends Component
{
    use WithFileUploads;

    public Contract $contract;

    public bool $isEnded = false;

    public string $move_out_date = '';

    /**
     * @var array<int, array{description:string,amount:string}>
     */
    public array $concepts = [];

    /**
     * @var array<int, UploadedFile|null>
     */
    public array $evidenceFiles = [];

    public ?string $lastSettlementPdfUrl = null;

    public ?string $lastSettlementSummary = null;

    #[On('deposit-hold-registered')]
    #[On('deposit-hold-voided')]
    public function onDepositHoldChanged(): void {}

    public function mount(Contract $contract): void
    {
        $this->contract = $contract;
        $this->isEnded = in_array($contract->status, [
            Contract::STATUS_ENDED,
            Contract::STATUS_CANCELLED,
        ], true);
        $this->move_out_date = now('America/Tijuana')->toDateString();
        $this->concepts = [
            ['description' => '', 'amount' => ''],
        ];
    }

    public function addConcept(): void
    {
        $this->concepts[] = ['description' => '', 'amount' => ''];
    }

    public function removeConcept(int $index): void
    {
        if (! array_key_exists($index, $this->concepts)) {
            return;
        }

        unset($this->concepts[$index], $this->evidenceFiles[$index]);

        $this->concepts = array_values($this->concepts);
        $this->evidenceFiles = array_values($this->evidenceFiles);
    }

    public function process(ProcessContractSettlementAction $action): void
    {
        if (! (auth()->user()?->can('contracts.settle') ?? false)) {
            abort(403);
        }

        if ($this->isEnded || in_array($this->contract->status, [
            Contract::STATUS_ENDED,
            Contract::STATUS_CANCELLED,
        ], true)) {
            $message = $this->contract->status === Contract::STATUS_CANCELLED
                ? __('contracts.settlement_cancelled_blocked')
                : __('contracts.settlement_ended_blocked');
            $this->addError('settlement_general', $message);

            return;
        }

        $this->validate([
            'move_out_date' => ['required', 'date'],
            'concepts' => ['required', 'array', 'min:1'],
            'concepts.*.description' => ['required', 'string', 'max:150'],
            'concepts.*.amount' => ['required', 'numeric', 'min:0.01'],
            'evidenceFiles.*' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        ], [
            'move_out_date.required' => __('contracts.validation.move_out_required'),
            'move_out_date.date' => __('contracts.validation.move_out_invalid'),
            'concepts.required' => __('contracts.validation.concepts_required'),
            'concepts.min' => __('contracts.validation.concepts_required'),
            'concepts.*.description.required' => __('contracts.validation.concept_required'),
            'concepts.*.description.max' => __('contracts.validation.concept_max'),
            'concepts.*.amount.required' => __('contracts.validation.concept_amount_required'),
            'concepts.*.amount.numeric' => __('contracts.validation.concept_amount_numeric'),
            'concepts.*.amount.min' => __('contracts.validation.concept_amount_min'),
            'evidenceFiles.*.max' => __('contracts.validation.evidence_max'),
            'evidenceFiles.*.mimes' => __('contracts.validation.evidence_mimes'),
        ]);

        $concepts = collect($this->concepts)
            ->map(function (array $row, int $index): array {
                return [
                    'description' => trim((string) ($row['description'] ?? '')),
                    'amount' => (float) ($row['amount'] ?? 0),
                    'evidence' => $this->evidenceFiles[$index] ?? null,
                ];
            })
            ->filter(fn (array $row): bool => $row['description'] !== '' && $row['amount'] > 0)
            ->values()
            ->all();

        if ($concepts === []) {
            $this->addError('concepts', __('contracts.validation.concept_valid_required'));

            return;
        }

        try {
            $result = $action->execute(
                contract: $this->contract,
                moveOutDate: $this->move_out_date,
                concepts: $concepts,
                userId: auth()->id(),
            );
        } catch (ValidationException $exception) {
            $message = $exception->errors()['month_close'][0] ?? __('contracts.validation.settlement_failed');
            $this->addError('settlement_general', $message);

            return;
        } catch (RuntimeException) {
            $this->isEnded = true;
            $this->addError('settlement_general', __('contracts.settlement_ended_blocked'));

            return;
        }

        $this->lastSettlementPdfUrl = route('contracts.settlements.pdf', [
            'contract' => $this->contract,
            'batch' => $result->batchId,
        ]);

        $this->lastSettlementSummary = __('contracts.settlement.summary', [
            'outstanding' => number_format($result->outstandingBeforeDeposit, 2),
            'applied' => number_format($result->depositApplied, 2),
            'refund' => number_format($result->depositRefund, 2),
            'balance' => number_format($result->balanceToCollect, 2),
        ]);

        $this->contract->refresh();
        $this->isEnded = true;

        session()->flash('success', __('contracts.flash.settlement_processed'));
        $this->dispatch('settlement-processed');
    }

    public function render(
        DepositBalanceService $depositBalanceService,
        LedgerOutstandingCalculator $ledgerOutstandingCalculator,
    ): View {
        $contract = Contract::query()
            ->with(['tenant:id,full_name', 'unit:id,name'])
            ->findOrFail($this->contract->id);

        $this->isEnded = in_array($contract->status, [
            Contract::STATUS_ENDED,
            Contract::STATUS_CANCELLED,
        ], true);

        $conceptsTotal = round(collect($this->concepts)
            ->sum(function (array $row): float {
                $description = trim((string) ($row['description'] ?? ''));
                $amount = (float) ($row['amount'] ?? 0);

                return ($description !== '' && $amount > 0) ? $amount : 0.0;
            }), 2);

        $availableDeposit = $depositBalanceService->availableDepositAmount($contract);
        $currentOutstanding = $depositBalanceService->outstandingBalanceExcludingDepositHold($contract);

        // outstandingBalance already nets live credit. Use gross pending so leftover
        // credit (credit > pending) is included once, matching settlement order:
        // apply credit → MOVEOUT → deposit apply → refund leftover credit + deposit surplus.
        $pendingBeforeCredit = $ledgerOutstandingCalculator->clampedPendingForContract(
            organizationId: (int) $contract->organization_id,
            contractId: (int) $contract->id,
        );
        $creditBalance = CreditBalance::query()
            ->where('organization_id', $contract->organization_id)
            ->where('contract_id', $contract->id)
            ->first();
        $creditBalanceAmount = round(max((float) ($creditBalance?->balance ?? 0), 0), 2);
        $remainingCredit = round(max(0, $creditBalanceAmount - $pendingBeforeCredit), 2);
        $projectedOutstanding = round($currentOutstanding + $conceptsTotal, 2);
        $depositSurplus = round(max(0, $availableDeposit - $projectedOutstanding), 2);
        $estimatedRefund = round($depositSurplus + $remainingCredit, 2);

        $refundedDeposit = $depositBalanceService->refundedDepositAmount($contract);
        $refundExpenseUrl = $refundedDeposit > 0
            ? route('expenses.index', ['contractFilter' => $contract->id])
            : null;

        $refundReceiptViewerItem = null;
        $batchId = data_get($contract->meta, 'settlement_batch_id');
        $documentId = (int) data_get($contract->meta, "settlements.{$batchId}.refund_receipt_document_id", 0);
        if (
            $documentId > 0
            && $refundedDeposit > 0
            && (auth()->user()?->can('documents.view') ?? false)
        ) {
            $receiptDocument = Document::query()
                ->where('id', $documentId)
                ->where('organization_id', $contract->organization_id)
                ->first();

            if ($receiptDocument !== null) {
                $refundReceiptViewerItem = FileViewerItem::fromDocumentRoute(
                    $receiptDocument->id,
                    __('contracts.view_deposit_refund_receipt'),
                    $receiptDocument->mime ?? 'application/pdf',
                );
            }
        }

        return view('livewire.contracts.settlement-wizard', [
            'contract' => $contract,
            'isEnded' => $this->isEnded,
            'availableDeposit' => $availableDeposit,
            'paidDeposit' => $depositBalanceService->paidDepositAmount($contract),
            'appliedDeposit' => $depositBalanceService->appliedDepositAmount($contract),
            'refundedDeposit' => $refundedDeposit,
            'currentOutstanding' => $currentOutstanding,
            'estimatedRefund' => $estimatedRefund,
            'refundExpenseUrl' => $refundExpenseUrl,
            'refundReceiptViewerItem' => $refundReceiptViewerItem,
        ]);
    }
}
