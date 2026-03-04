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
        $this->contract = $contract;
        $this->paid_at = now()->format('Y-m-d\TH:i');
    }

    public function save(RegisterContractPaymentAction $action): mixed
    {
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
            $message = $exception->errors()['month_close'][0] ?? 'No se pudo registrar el pago.';
            $this->addError('month_close', $message);

            return null;
        }

        session()->flash('success', 'Pago registrado correctamente.');

        return redirect()->route('payments.show', $payment);
    }

    public function render(): View
    {
        return view('livewire.payments.create')
            ->layout('layouts.app', ['title' => 'Registrar pago']);
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
            'amount.required' => 'El monto es obligatorio.',
            'amount.numeric' => 'El monto debe ser numérico.',
            'amount.min' => 'El monto debe ser mayor a cero.',
            'method.required' => 'Selecciona un método de pago.',
            'method.in' => 'El método de pago no es válido.',
            'paid_at.required' => 'La fecha de pago es obligatoria.',
            'paid_at.date' => 'La fecha de pago no es válida.',
            'reference.max' => 'La referencia no debe exceder 120 caracteres.',
            'evidence.max' => 'La evidencia no debe exceder 5 MB.',
            'evidence.mimes' => 'La evidencia debe ser JPG, PNG o PDF.',
        ];
    }
}
