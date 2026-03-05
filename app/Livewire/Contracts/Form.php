<?php

namespace App\Livewire\Contracts;

use App\Models\Contract;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    private const MAX_DAILY_RATE_DECIMAL = 0.5;

    public ?int $contractId = null;

    public ?int $unit_id = null;

    public ?int $tenant_id = null;

    public string $rent_amount = '';

    public string $deposit_amount = '0.00';

    public string $due_day = '1';

    public string $grace_days = '5';

    public string $penalty_rate_daily = '5.0000';

    public string $status = Contract::STATUS_ACTIVE;

    public string $starts_at = '';

    public ?string $ends_at = null;

    public ?string $meta_notes = null;

    public function mount(?Contract $contract = null): void
    {
        if (! ($contract instanceof Contract) || ! $contract->exists) {
            $this->starts_at = now()->toDateString();

            $requestedUnitId = request()->integer('unit_id');
            if ($requestedUnitId > 0) {
                $unit = Unit::query()
                    ->where('status', 'active')
                    ->find($requestedUnitId);

                if ($unit !== null) {
                    $this->unit_id = $unit->id;
                }
            }

            return;
        }

        $this->contractId = $contract->id;
        $this->unit_id = $contract->unit_id;
        $this->tenant_id = $contract->tenant_id;
        $this->rent_amount = (string) $contract->rent_amount;
        $this->deposit_amount = (string) $contract->deposit_amount;
        $this->due_day = (string) $contract->due_day;
        $this->grace_days = (string) $contract->grace_days;
        $this->penalty_rate_daily = $this->toDisplayPenaltyRate((float) $contract->penalty_rate_daily);
        $this->status = $contract->status;
        $this->starts_at = optional($contract->starts_at)->format('Y-m-d') ?: now()->toDateString();
        $this->ends_at = optional($contract->ends_at)->format('Y-m-d');
        $this->meta_notes = data_get($contract->meta, 'notes');
    }

    public function save(): mixed
    {
        $validated = $this->validate($this->rules(), $this->messages());

        $normalizedPenaltyRate = $this->normalizePenaltyRateDaily((float) $validated['penalty_rate_daily']);

        if ($normalizedPenaltyRate <= 0 || $normalizedPenaltyRate > 1) {
            $this->addError('penalty_rate_daily', 'La tasa diaria de multa normalizada debe ser mayor a 0% y menor o igual a 100%.');

            return null;
        }

        if ($normalizedPenaltyRate > self::MAX_DAILY_RATE_DECIMAL) {
            $this->addError('penalty_rate_daily', 'Por seguridad, la tasa diaria de multa no puede exceder 50%.');

            return null;
        }

        $validated['penalty_rate_daily'] = $normalizedPenaltyRate;
        $this->penalty_rate_daily = $this->toDisplayPenaltyRate($normalizedPenaltyRate);

        try {
            $contract = DB::transaction(function () use ($validated): Contract {
                $unit = Unit::query()->findOrFail((int) $validated['unit_id']);
                $tenant = Tenant::query()->findOrFail((int) $validated['tenant_id']);

                $contract = $this->contractId !== null
                    ? Contract::query()->findOrFail($this->contractId)
                    : new Contract;

                $contract->organization_id = auth()->user()?->organization_id;
                $contract->unit()->associate($unit);
                $contract->tenant()->associate($tenant);
                $contract->rent_amount = $validated['rent_amount'];
                $contract->deposit_amount = $validated['deposit_amount'];
                $contract->due_day = (int) $validated['due_day'];
                $contract->grace_days = (int) $validated['grace_days'];
                $contract->penalty_rate_daily = $validated['penalty_rate_daily'];
                $contract->status = $validated['status'];
                $contract->starts_at = $validated['starts_at'];
                $contract->ends_at = $validated['ends_at'] ?: null;
                $contract->meta = [
                    'notes' => $validated['meta_notes'] ?: null,
                ];
                $contract->save();

                return $contract;
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23000') {
                $this->addError('unit_id', 'La unidad ya cuenta con un contrato activo.');

                return null;
            }

            throw $exception;
        }

        $isNew = $this->contractId === null;
        $action = $isNew ? 'contract.created' : 'contract.updated';
        $message = $isNew ? 'Contrato creado correctamente.' : 'Contrato actualizado correctamente.';

        app(AuditLogger::class)->log(
            action: $action,
            auditable: $contract,
            summary: sprintf(
                'Contrato #%d %s para unidad #%d',
                $contract->id,
                $isNew ? 'creado' : 'actualizado',
                $contract->unit_id,
            ),
            meta: [
                'contract_id' => $contract->id,
                'unit_id' => $contract->unit_id,
                'tenant_id' => $contract->tenant_id,
                'rent_amount' => (float) $contract->rent_amount,
                'status' => $contract->status,
                'starts_at' => $contract->starts_at?->toDateString(),
            ],
        );

        session()->flash('success', $message);

        return redirect()->route('contracts.show', $contract);
    }

    public function render(): View
    {
        $units = Unit::query()
            ->where('status', 'active')
            ->with('property:id,name')
            ->orderBy('property_id')
            ->orderBy('name')
            ->get(['id', 'property_id', 'name', 'code']);

        $tenants = Tenant::query()
            ->where('status', 'active')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'email']);

        return view('livewire.contracts.form', [
            'units' => $units,
            'tenants' => $tenants,
            'isEdit' => $this->contractId !== null,
        ])->layout('layouts.app', [
            'title' => $this->contractId !== null ? 'Editar contrato' : 'Nuevo contrato',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'unit_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->status !== Contract::STATUS_ACTIVE) {
                        return;
                    }

                    $query = Contract::query()
                        ->where('unit_id', $value)
                        ->where('status', Contract::STATUS_ACTIVE);

                    if ($this->contractId !== null) {
                        $query->whereKeyNot($this->contractId);
                    }

                    if ($query->exists()) {
                        $fail('La unidad ya cuenta con un contrato activo.');
                    }
                },
            ],
            'tenant_id' => ['required', 'integer'],
            'rent_amount' => ['required', 'numeric', 'min:0'],
            'deposit_amount' => ['required', 'numeric', 'min:0'],
            'due_day' => ['required', 'integer', 'min:1', 'max:31'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:31'],
            'penalty_rate_daily' => ['required', 'numeric', 'min:0.0001', 'max:100'],
            'status' => ['required', Rule::in([Contract::STATUS_ACTIVE, Contract::STATUS_ENDED])],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'meta_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'unit_id.required' => 'Selecciona una unidad.',
            'tenant_id.required' => 'Selecciona un inquilino.',
            'rent_amount.required' => 'La renta mensual es obligatoria.',
            'rent_amount.numeric' => 'La renta mensual debe ser numérica.',
            'rent_amount.min' => 'La renta mensual no puede ser negativa.',
            'deposit_amount.required' => 'El depósito es obligatorio.',
            'deposit_amount.numeric' => 'El depósito debe ser numérico.',
            'deposit_amount.min' => 'El depósito no puede ser negativo.',
            'due_day.required' => 'El día de vencimiento es obligatorio.',
            'due_day.integer' => 'El día de vencimiento debe ser un número entero.',
            'due_day.min' => 'El día de vencimiento debe ser mayor o igual a 1.',
            'due_day.max' => 'El día de vencimiento debe ser menor o igual a 31.',
            'grace_days.required' => 'Los días de gracia son obligatorios.',
            'grace_days.integer' => 'Los días de gracia deben ser un número entero.',
            'grace_days.min' => 'Los días de gracia no pueden ser negativos.',
            'grace_days.max' => 'Los días de gracia no deben exceder 31.',
            'penalty_rate_daily.required' => 'La tasa diaria de multa es obligatoria.',
            'penalty_rate_daily.numeric' => 'La tasa diaria de multa debe ser numérica.',
            'penalty_rate_daily.min' => 'La tasa diaria de multa debe ser mayor a 0.',
            'penalty_rate_daily.max' => 'La tasa diaria de multa no debe exceder 100.',
            'status.required' => 'Selecciona el estado del contrato.',
            'status.in' => 'El estado seleccionado no es válido.',
            'starts_at.required' => 'La fecha de inicio es obligatoria.',
            'starts_at.date' => 'La fecha de inicio no es válida.',
            'ends_at.date' => 'La fecha de fin no es válida.',
            'ends_at.after_or_equal' => 'La fecha de fin debe ser igual o posterior al inicio.',
            'meta_notes.max' => 'Las notas no deben exceder 1000 caracteres.',
        ];
    }

    private function normalizePenaltyRateDaily(float $value): float
    {
        if ($value > 1) {
            return round($value / 100, 6);
        }

        return round($value, 6);
    }

    private function toDisplayPenaltyRate(float $storedDecimalRate): string
    {
        return number_format($storedDecimalRate * 100, 4, '.', '');
    }
}
