<?php

namespace App\Livewire\Contracts;

use App\Actions\Contracts\RegisterDepositHoldAction;
use App\Actions\Contracts\VoidDepositHoldAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\Payment;
use App\Support\DateDisplay;
use App\Support\DepositBalanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class DepositHoldForm extends Component
{
    public Contract $contract;

    public string $deposit_received_at = '';

    public string $deposit_amount = '';

    public string $deposit_method = Payment::METHOD_TRANSFER;

    public ?string $deposit_notes = null;

    public bool $showVoidConfirm = false;

    public ?int $voidingChargeId = null;

    public ?int $evidenceChargeId = null;

    public function mount(Contract $contract, DepositBalanceService $depositBalanceService): void
    {
        $this->contract = $contract;
        $this->deposit_received_at = now('America/Tijuana')->toDateString();
        $this->deposit_amount = number_format(
            $depositBalanceService->remainingDepositHoldAmount($contract),
            2,
            '.',
            ''
        );
    }

    #[On('deposit-hold-registered')]
    #[On('deposit-hold-voided')]
    public function onDepositHoldChanged(): void
    {
        // Re-render; remaining/prefill refreshed in render().
    }

    public function registerDeposit(RegisterDepositHoldAction $action): void
    {
        if (! (auth()->user()?->can('charges.manage') ?? false)) {
            abort(403);
        }

        $validated = $this->validate([
            'deposit_received_at' => ['required', 'date'],
            'deposit_amount' => ['required', 'numeric', 'min:0.01'],
            'deposit_method' => ['required', Rule::in([Payment::METHOD_CASH, Payment::METHOD_TRANSFER])],
            'deposit_notes' => ['nullable', 'string', 'max:500'],
        ], [
            'deposit_received_at.required' => __('contracts.validation.deposit_received_required'),
            'deposit_received_at.date' => __('contracts.validation.deposit_received_invalid'),
            'deposit_amount.required' => __('contracts.validation.deposit_amount_required'),
            'deposit_amount.numeric' => __('contracts.validation.deposit_amount_numeric'),
            'deposit_amount.min' => __('contracts.validation.deposit_amount_min'),
            'deposit_method.required' => __('contracts.validation.deposit_method_required'),
            'deposit_method.in' => __('contracts.validation.deposit_method_invalid'),
            'deposit_notes.max' => __('contracts.validation.deposit_notes_max'),
        ]);

        try {
            $action->execute(
                contract: $this->contract,
                amount: (float) $validated['deposit_amount'],
                receivedAt: $validated['deposit_received_at'],
                notes: $validated['deposit_notes'] ?? null,
                userId: auth()->id(),
                method: $validated['deposit_method'],
            );
        } catch (ValidationException $exception) {
            $message = $exception->errors()['month_close'][0]
                ?? $exception->errors()['deposit_amount'][0]
                ?? $exception->errors()['deposit_method'][0]
                ?? __('contracts.validation.deposit_failed');
            $this->addError('deposit_general', $message);

            return;
        }

        $this->reset('deposit_notes');
        session()->flash('success', __('contracts.flash.deposit_registered'));
        $this->deposit_amount = number_format(
            app(DepositBalanceService::class)->remainingDepositHoldAmount($this->contract->fresh()),
            2,
            '.',
            ''
        );
        $this->dispatch('deposit-hold-registered');
    }

    public function confirmVoidDeposit(int $chargeId): void
    {
        if (! (auth()->user()?->can('charges.manage') ?? false)) {
            abort(403);
        }

        $this->voidingChargeId = $chargeId;
        $this->showVoidConfirm = true;
    }

    public function cancelVoidDeposit(): void
    {
        $this->showVoidConfirm = false;
        $this->voidingChargeId = null;
    }

    public function executeVoidDeposit(VoidDepositHoldAction $action): void
    {
        if (! (auth()->user()?->can('charges.manage') ?? false)) {
            abort(403);
        }

        if ($this->voidingChargeId === null) {
            $this->cancelVoidDeposit();

            return;
        }

        try {
            $action->execute(
                contract: $this->contract,
                chargeId: $this->voidingChargeId,
                userId: auth()->id(),
            );
        } catch (ValidationException $exception) {
            $message = $exception->errors()['month_close'][0]
                ?? $exception->errors()['deposit_void'][0]
                ?? __('contracts.validation.deposit_void_failed');
            $this->addError('deposit_general', $message);
            $this->cancelVoidDeposit();

            return;
        }

        if ($this->evidenceChargeId === $this->voidingChargeId) {
            $this->evidenceChargeId = null;
        }

        $this->cancelVoidDeposit();
        session()->flash('success', __('contracts.flash.deposit_voided'));
        $this->deposit_amount = number_format(
            app(DepositBalanceService::class)->remainingDepositHoldAmount($this->contract->fresh()),
            2,
            '.',
            ''
        );
        $this->dispatch('deposit-hold-voided');
    }

    public function toggleEvidence(int $chargeId): void
    {
        $this->evidenceChargeId = $this->evidenceChargeId === $chargeId ? null : $chargeId;
    }

    public function render(DepositBalanceService $depositBalanceService): View
    {
        $contract = Contract::query()->findOrFail($this->contract->id);
        $registered = $depositBalanceService->registeredDepositHoldAmount($contract);
        $remaining = $depositBalanceService->remainingDepositHoldAmount($contract);

        if ($remaining > 0 && (float) $this->deposit_amount <= 0) {
            $this->deposit_amount = number_format($remaining, 2, '.', '');
        }

        return view('livewire.contracts.deposit-hold-form', [
            'contractDepositAmount' => (float) $contract->deposit_amount,
            'registeredDeposit' => $registered,
            'remainingDeposit' => $remaining,
            'depositHolds' => $this->activeDepositHolds($contract),
            'canManageCharges' => auth()->user()?->can('charges.manage') ?? false,
            'canViewDocuments' => auth()->user()?->can('documents.view') ?? false,
        ]);
    }

    /**
     * @return Collection<int, array{id:int, charge_date:?string, amount:float, notes:?string, receipt_folio:?string, receipt_url:?string}>
     */
    private function activeDepositHolds(Contract $contract): Collection
    {
        return Charge::query()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_DEPOSIT_HOLD)
            ->orderBy('charge_date')
            ->orderBy('id')
            ->get()
            ->map(function (Charge $hold): array {
                $folio = data_get($hold->meta, 'deposit_receipt_folio');

                return [
                    'id' => $hold->id,
                    'charge_date' => DateDisplay::formatDate($hold->charge_date),
                    'amount' => (float) $hold->amount,
                    'notes' => data_get($hold->meta, 'notes'),
                    'receipt_folio' => is_string($folio) ? $folio : null,
                    'receipt_url' => is_string($folio) && $folio !== ''
                        ? route('deposits.receipt.pdf', ['chargeId' => $hold->id])
                        : null,
                ];
            });
    }
}
