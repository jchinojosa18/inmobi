<?php

namespace App\Livewire\Payments;

use App\Actions\Payments\RegisterContractPaymentAction;
use App\Models\Contract;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

class Create extends Component
{
    use WithFileUploads;

    public Contract $contract;

    public string $amount = '';

    public string $method = Payment::METHOD_TRANSFER;

    public string $paid_at = '';

    public ?string $reference = null;

    public $evidence = null;

    public function mount(Contract $contract): void
    {
        if (! (auth()->user()?->can('payments.create') ?? false)) {
            abort(403);
        }

        $this->contract = $contract;
        $this->paid_at = now()->format('Y-m-d\TH:i');
    }

    public function save(RegisterContractPaymentAction $action): mixed
    {
        if (! (auth()->user()?->can('payments.create') ?? false)) {
            abort(403);
        }

        $validated = $this->validate($this->rules(), $this->messages());

        try {
            $payment = $action->execute(
                contract: $this->contract,
                data: [
                    'amount' => $validated['amount'],
                    'method' => $validated['method'],
                    'paid_at' => $validated['paid_at'],
                    'reference' => $validated['reference'] ?: null,
                ],
                evidence: $validated['evidence'] ?? null
            );
        } catch (ValidationException $exception) {
            $message = $exception->errors()['month_close'][0] ?? __('finance.validation.payment_failed');
            $this->addError('month_close', $message);

            return null;
        }

        session()->flash('success', __('finance.flash.payment_created'));

        return redirect()->route('payments.show', $payment);
    }

    public function render(): View
    {
        return view('livewire.payments.create')
            ->layout('layouts.app', ['title' => __('finance.payments.title')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in([Payment::METHOD_CASH, Payment::METHOD_TRANSFER])],
            'paid_at' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:120'],
            'evidence' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'amount.required' => __('finance.validation.amount_required'),
            'amount.numeric' => __('finance.validation.amount_numeric'),
            'amount.min' => __('finance.validation.amount_min'),
            'method.required' => __('finance.validation.method_required'),
            'method.in' => __('finance.validation.method_invalid'),
            'paid_at.required' => __('finance.validation.paid_at_required'),
            'paid_at.date' => __('finance.validation.paid_at_invalid'),
            'reference.max' => __('finance.validation.reference_max'),
            'evidence.max' => __('finance.validation.evidence_max'),
            'evidence.mimes' => __('finance.validation.evidence_mimes'),
        ];
    }
}
