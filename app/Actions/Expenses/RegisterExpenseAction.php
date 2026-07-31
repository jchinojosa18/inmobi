<?php

namespace App\Actions\Expenses;

use App\Models\Contract;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Unit;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RegisterExpenseAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $organizationId, array $data): Expense
    {
        $validated = Validator::make($data, [
            'expense_category_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'spent_at' => ['required', 'date'],
            'unit_id' => ['nullable', 'integer'],
            'contract_id' => ['nullable', 'integer'],
            'vendor' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'meta' => ['nullable', 'array'],
        ])->validate();

        $category = ExpenseCategory::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->whereKey($validated['expense_category_id'])
            ->where('is_active', true)
            ->first();

        if ($category === null) {
            throw ValidationException::withMessages([
                'expense_category_id' => [__('finance.validation.category_invalid')],
            ]);
        }

        $unitId = isset($validated['unit_id']) ? (int) $validated['unit_id'] : null;
        $contractId = isset($validated['contract_id']) ? (int) $validated['contract_id'] : null;

        if ($unitId !== null) {
            $unitExists = Unit::query()
                ->withoutOrganizationScope()
                ->where('organization_id', $organizationId)
                ->whereKey($unitId)
                ->exists();

            if (! $unitExists) {
                throw ValidationException::withMessages([
                    'unit_id' => [__('finance.validation.unit_invalid')],
                ]);
            }
        }

        if ($contractId !== null) {
            $contract = Contract::query()
                ->withoutOrganizationScope()
                ->where('organization_id', $organizationId)
                ->whereKey($contractId)
                ->first();

            if ($contract === null) {
                throw ValidationException::withMessages([
                    'contract_id' => [__('finance.validation.contract_invalid')],
                ]);
            }

            if ($unitId === null) {
                throw ValidationException::withMessages([
                    'unit_id' => [__('finance.validation.unit_required_for_contract')],
                ]);
            }

            if ((int) $contract->unit_id !== $unitId) {
                throw ValidationException::withMessages([
                    'contract_id' => [__('finance.validation.contract_unit_mismatch')],
                ]);
            }
        }

        return Expense::query()->create([
            'organization_id' => $organizationId,
            'unit_id' => $unitId,
            'expense_category_id' => (int) $validated['expense_category_id'],
            'contract_id' => $contractId,
            'amount' => $validated['amount'],
            'spent_at' => $validated['spent_at'],
            'vendor' => ($validated['vendor'] ?? null) ?: null,
            'notes' => ($validated['notes'] ?? null) ?: null,
            'meta' => $validated['meta'] ?? [],
        ]);
    }
}
