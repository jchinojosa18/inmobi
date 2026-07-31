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
use Livewire\Attributes\On;
use Livewire\Component;

class CreateModal extends Component
{
    private const MAX_DAILY_RATE_DECIMAL = 0.5;

    public bool $open = false;

    public ?int $contractId = null;

    public ?int $unit_id = null;

    public ?int $tenant_id = null;

    public string $rent_amount = '';

    public string $deposit_amount = '';

    public string $due_day = '';

    public string $grace_days = '';

    public string $penalty_rate_daily = '';

    public string $status = Contract::STATUS_ACTIVE;

    public string $starts_at = '';

    public ?string $ends_at = null;

    public ?string $meta_notes = null;

    #[On('open-contract-create')]
    public function open(?int $unitId = null): void
    {
        if (! (auth()->user()?->can('contracts.manage') ?? false)) {
            abort(403);
        }

        $this->resetForm();
        $this->open = true;

        if ($unitId !== null && $unitId > 0) {
            $unit = Unit::query()
                ->where('status', 'active')
                ->whereDoesntHave('contracts', function ($query): void {
                    $query->where('status', Contract::STATUS_ACTIVE);
                })
                ->find($unitId);

            if ($unit !== null) {
                $this->unit_id = $unit->id;
            }
        }
    }

    #[On('open-contract-edit')]
    public function openEdit(int $contractId): void
    {
        if (! (auth()->user()?->can('contracts.manage') ?? false)) {
            abort(403);
        }

        $contract = Contract::query()->findOrFail($contractId);

        $this->resetForm();
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
        $this->open = true;
    }

    public function cancelForm(): void
    {
        $this->close();
    }

    public function close(): void
    {
        $this->open = false;
        $this->resetForm();
    }

    public function save(): mixed
    {
        if (! (auth()->user()?->can('contracts.manage') ?? false)) {
            abort(403);
        }

        $validated = $this->validate($this->rules(), $this->messages());

        $normalizedPenaltyRate = $this->normalizePenaltyRateDaily((float) $validated['penalty_rate_daily']);

        if ($normalizedPenaltyRate <= 0 || $normalizedPenaltyRate > 1) {
            $this->addError('penalty_rate_daily', __('contracts.validation.penalty_rate_normalized'));

            return null;
        }

        if ($normalizedPenaltyRate > self::MAX_DAILY_RATE_DECIMAL) {
            $this->addError('penalty_rate_daily', __('contracts.validation.penalty_rate_security'));

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

                if ($this->contractId === null) {
                    $contract->unit()->associate($unit);
                    $contract->tenant()->associate($tenant);
                }

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
                $this->addError('unit_id', __('contracts.validation.unit_active_contract'));

                return null;
            }

            throw $exception;
        }

        $isNew = $this->contractId === null;

        app(AuditLogger::class)->log(
            action: $isNew ? 'contract.created' : 'contract.updated',
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

        session()->flash('success', $isNew
            ? __('contracts.flash.contract_created')
            : __('contracts.flash.contract_updated'));

        $this->close();

        if ($isNew) {
            return redirect()->route('contracts.show', $contract);
        }

        $this->dispatch('contract-updated');

        return null;
    }

    public function render(): View
    {
        $unitsQuery = Unit::query()
            ->where('status', 'active')
            ->with('property:id,name')
            ->orderBy('property_id')
            ->orderBy('name');

        if ($this->contractId === null) {
            $unitsQuery->whereDoesntHave('contracts', function ($query): void {
                $query->where('status', Contract::STATUS_ACTIVE);
            });
        } else {
            $unitsQuery->whereKey($this->unit_id);
        }

        $units = $unitsQuery->get(['id', 'property_id', 'name', 'code']);

        $tenantsQuery = Tenant::query()->orderBy('full_name');

        if ($this->contractId === null) {
            $tenantsQuery->where('status', 'active');
        } else {
            $tenantsQuery->whereKey($this->tenant_id);
        }

        $tenants = $tenantsQuery->get(['id', 'full_name', 'email']);

        return view('livewire.contracts.create-modal', [
            'units' => $units,
            'tenants' => $tenants,
            'isEdit' => $this->contractId !== null,
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
                        $fail(__('contracts.validation.unit_active_contract'));
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
            'unit_id.required' => __('contracts.validation.unit_required'),
            'tenant_id.required' => __('contracts.validation.tenant_required'),
            'rent_amount.required' => __('contracts.validation.rent_required'),
            'rent_amount.numeric' => __('contracts.validation.rent_numeric'),
            'rent_amount.min' => __('contracts.validation.rent_min'),
            'deposit_amount.required' => __('contracts.validation.deposit_required'),
            'deposit_amount.numeric' => __('contracts.validation.deposit_numeric'),
            'deposit_amount.min' => __('contracts.validation.deposit_min'),
            'due_day.required' => __('contracts.validation.due_day_required'),
            'due_day.integer' => __('contracts.validation.due_day_integer'),
            'due_day.min' => __('contracts.validation.due_day_min'),
            'due_day.max' => __('contracts.validation.due_day_max'),
            'grace_days.required' => __('contracts.validation.grace_days_required'),
            'grace_days.integer' => __('contracts.validation.grace_days_integer'),
            'grace_days.min' => __('contracts.validation.grace_days_min'),
            'grace_days.max' => __('contracts.validation.grace_days_max'),
            'penalty_rate_daily.required' => __('contracts.validation.penalty_rate_required'),
            'penalty_rate_daily.numeric' => __('contracts.validation.penalty_rate_numeric'),
            'penalty_rate_daily.min' => __('contracts.validation.penalty_rate_min'),
            'penalty_rate_daily.max' => __('contracts.validation.penalty_rate_max'),
            'status.required' => __('contracts.validation.status_required'),
            'status.in' => __('contracts.validation.status_invalid'),
            'starts_at.required' => __('contracts.validation.starts_at_required'),
            'starts_at.date' => __('contracts.validation.starts_at_invalid'),
            'ends_at.date' => __('contracts.validation.ends_at_invalid'),
            'ends_at.after_or_equal' => __('contracts.validation.ends_at_after_start'),
            'meta_notes.max' => __('contracts.validation.notes_max'),
        ];
    }

    private function normalizePenaltyRateDaily(float $value): float
    {
        if ($value > 1) {
            return round(round($value, 2) / 100, 4);
        }

        return round($value, 4);
    }

    private function toDisplayPenaltyRate(float $storedDecimalRate): string
    {
        return number_format($storedDecimalRate * 100, 2, '.', '');
    }

    private function resetForm(): void
    {
        $this->reset([
            'contractId',
            'unit_id',
            'tenant_id',
            'rent_amount',
            'deposit_amount',
            'due_day',
            'grace_days',
            'penalty_rate_daily',
            'status',
            'starts_at',
            'ends_at',
            'meta_notes',
        ]);

        $this->deposit_amount = '';
        $this->due_day = '';
        $this->grace_days = '';
        $this->penalty_rate_daily = '';
        $this->status = Contract::STATUS_ACTIVE;
        $this->starts_at = now()->toDateString();
        $this->resetValidation();
    }
}
