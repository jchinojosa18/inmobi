<?php

namespace App\Livewire\Contracts;

use App\Actions\Contracts\RegisterDepositHoldAction;
use App\Models\Contract;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class DepositHoldForm extends Component
{
    public Contract $contract;

    public string $deposit_received_at = '';

    public string $deposit_amount = '';

    public ?string $deposit_notes = null;

    public function mount(Contract $contract): void
    {
        $this->contract = $contract;
        $this->deposit_received_at = now('America/Tijuana')->toDateString();
        $this->deposit_amount = number_format((float) $contract->deposit_amount, 2, '.', '');
    }

    public function registerDeposit(RegisterDepositHoldAction $action): void
    {
        if (! (auth()->user()?->can('charges.manage') ?? false)) {
            abort(403);
        }

        $validated = $this->validate([
            'deposit_received_at' => ['required', 'date'],
            'deposit_amount' => ['required', 'numeric', 'min:0.01'],
            'deposit_notes' => ['nullable', 'string', 'max:500'],
        ], [
            'deposit_received_at.required' => 'La fecha de recepción es obligatoria.',
            'deposit_received_at.date' => 'La fecha de recepción no es válida.',
            'deposit_amount.required' => 'El monto del depósito es obligatorio.',
            'deposit_amount.numeric' => 'El monto del depósito debe ser numérico.',
            'deposit_amount.min' => 'El monto del depósito debe ser mayor a cero.',
            'deposit_notes.max' => 'Las notas no deben exceder 500 caracteres.',
        ]);

        try {
            $action->execute(
                contract: $this->contract,
                amount: (float) $validated['deposit_amount'],
                receivedAt: $validated['deposit_received_at'],
                notes: $validated['deposit_notes'] ?? null,
                userId: auth()->id(),
            );
        } catch (ValidationException $exception) {
            $message = $exception->errors()['month_close'][0] ?? $exception->errors()['deposit_amount'][0] ?? 'No se pudo registrar el depósito.';
            $this->addError('deposit_general', $message);

            return;
        }

        $this->reset('deposit_notes');
        session()->flash('success', 'Depósito en garantía registrado correctamente.');
        $this->dispatch('deposit-hold-registered');
    }

    public function render(): View
    {
        return view('livewire.contracts.deposit-hold-form');
    }
}
