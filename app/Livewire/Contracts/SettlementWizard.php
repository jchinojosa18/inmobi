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
        $this->validate([
            'move_out_date' => ['required', 'date'],
            'concepts' => ['required', 'array', 'min:1'],
            'concepts.*.description' => ['required', 'string', 'max:150'],
            'concepts.*.amount' => ['required', 'numeric', 'min:0.01'],
            'evidenceFiles.*' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        ], [
            'move_out_date.required' => 'La fecha de salida es obligatoria.',
            'move_out_date.date' => 'La fecha de salida no es válida.',
            'concepts.required' => 'Agrega al menos un concepto de finiquito.',
            'concepts.min' => 'Agrega al menos un concepto de finiquito.',
            'concepts.*.description.required' => 'El concepto es obligatorio.',
            'concepts.*.description.max' => 'El concepto no debe exceder 150 caracteres.',
            'concepts.*.amount.required' => 'El monto del concepto es obligatorio.',
            'concepts.*.amount.numeric' => 'El monto del concepto debe ser numérico.',
            'concepts.*.amount.min' => 'El monto del concepto debe ser mayor a cero.',
            'evidenceFiles.*.max' => 'Cada evidencia debe pesar máximo 5 MB.',
            'evidenceFiles.*.mimes' => 'Las evidencias deben ser JPG, PNG o WEBP.',
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
            $this->addError('concepts', 'Agrega al menos un concepto válido.');

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
            $message = $exception->errors()['month_close'][0] ?? 'No se pudo procesar el finiquito.';
            $this->addError('settlement_general', $message);

            return;
        }

        $this->lastSettlementPdfUrl = route('contracts.settlements.pdf', [
            'contract' => $this->contract,
            'batch' => $result->batchId,
        ]);

        $this->lastSettlementSummary = sprintf(
            'Adeudo previo: $%s | Depósito aplicado: $%s | Devolución: $%s | Saldo por cobrar: $%s',
            number_format($result->outstandingBeforeDeposit, 2),
            number_format($result->depositApplied, 2),
            number_format($result->depositRefund, 2),
            number_format($result->balanceToCollect, 2),
        );

        session()->flash('success', 'Finiquito procesado correctamente.');
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
