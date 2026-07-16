<?php

namespace App\Livewire\Contracts;

use App\Actions\Contracts\ProcessContractSettlementAction;
use App\Models\Contract;
use App\Support\DepositBalanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

class SettlementWizard extends Component
{
    use WithFileUploads;

    public Contract $contract;

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

    public function mount(Contract $contract): void
    {
        $this->contract = $contract;
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

        session()->flash('success', __('contracts.flash.settlement_processed'));
        $this->dispatch('settlement-processed');
    }

    public function render(DepositBalanceService $depositBalanceService): View
    {
        $contract = Contract::query()
            ->with(['tenant:id,full_name', 'unit:id,name'])
            ->findOrFail($this->contract->id);

        return view('livewire.contracts.settlement-wizard', [
            'contract' => $contract,
            'availableDeposit' => $depositBalanceService->availableDepositAmount($contract),
            'paidDeposit' => $depositBalanceService->paidDepositAmount($contract),
            'appliedDeposit' => $depositBalanceService->appliedDepositAmount($contract),
            'refundedDeposit' => $depositBalanceService->refundedDepositAmount($contract),
            'currentOutstanding' => $depositBalanceService->outstandingBalanceExcludingDepositHold($contract),
        ]);
    }
}
