<?php

namespace App\Livewire\Expenses;

use App\Actions\Expenses\RegisterExpenseAction;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Unit;
use App\Support\AuditLogger;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class QuickRegisterModal extends Component
{
    use WithFileUploads;

    public bool $open = false;

    public string $spentAt = '';

    public string $amount = '';

    public ?int $expenseCategoryId = null;

    public ?int $unitId = null;

    public ?int $contractId = null;

    public string $vendor = '';

    public string $notes = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $evidenceFile = null;

    #[On('open-quick-expense')]
    public function open(?int $unitId = null): void
    {
        if (! (auth()->user()?->can('expenses.create') ?? false)) {
            abort(403);
        }

        $this->resetForm();
        $this->open = true;

        if ($unitId !== null) {
            $this->unitId = $unitId;
        }

        $this->dispatch('qem-opened');
    }

    #[On('close-quick-expense')]
    public function close(): void
    {
        $this->open = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->spentAt = now()->toDateString();
        $this->amount = '';
        $this->expenseCategoryId = null;
        $this->unitId = null;
        $this->contractId = null;
        $this->vendor = '';
        $this->notes = '';
        $this->evidenceFile = null;
        $this->resetValidation();
        $this->dispatch('expense-evidence-reset');
    }

    public function updatedUnitId(): void
    {
        $this->contractId = null;
        $this->resetValidation(['contractId']);
    }

    public function save(): void
    {
        if (! (auth()->user()?->can('expenses.create') ?? false)) {
            abort(403);
        }

        $this->validate($this->rules(), $this->messages());

        $organizationId = (int) auth()->user()?->organization_id;

        try {
            $expense = app(RegisterExpenseAction::class)->execute($organizationId, [
                'expense_category_id' => $this->expenseCategoryId,
                'amount' => $this->amount,
                'spent_at' => $this->spentAt,
                'unit_id' => $this->unitId,
                'contract_id' => $this->contractId,
                'vendor' => $this->vendor ?: null,
                'notes' => $this->notes ?: null,
                'meta' => [],
            ]);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0] ?? '');
            }

            if ($e->errors()['month_close'][0] ?? null) {
                $this->addError('month_close', $e->errors()['month_close'][0]);
            }

            return;
        }

        if ($this->evidenceFile !== null) {
            $disk = (string) config('filesystems.documents_disk', 'local');
            $path = $this->evidenceFile->store('documents/expenses/'.$organizationId, $disk);

            Document::storeNew([
                'organization_id' => $organizationId,
                'documentable_id' => $expense->id,
                'documentable_type' => Expense::class,
                'path' => $path,
                'mime' => $this->evidenceFile->getMimeType() ?: 'application/octet-stream',
                'size' => $this->evidenceFile->getSize() ?: 0,
                'type' => 'EXPENSE_EVIDENCE',
                'tags' => ['expense', 'evidence'],
                'meta' => [
                    'disk' => $disk,
                    'uploaded_at' => now()->toISOString(),
                ],
            ]);
        }

        $expense->load('expenseCategory');

        app(AuditLogger::class)->log(
            action: 'expense.created',
            auditable: $expense,
            summary: __('finance.expenses.audit_summary', [
                'amount' => number_format((float) $expense->amount, 2),
                'category' => $expense->expenseCategory?->name ?? '—',
            ]),
            meta: [
                'amount' => (float) $expense->amount,
                'expense_category_id' => $expense->expense_category_id,
                'spent_at' => $expense->spent_at,
                'unit_id' => $expense->unit_id,
                'contract_id' => $expense->contract_id,
                'vendor' => $expense->vendor,
            ],
        );

        $this->open = false;
        $this->resetForm();
        $this->dispatch('expense-created');
    }

    public function render(): View
    {
        $categories = ExpenseCategory::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        $unitsQuery = Unit::query()
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->orderBy('properties.name')
            ->orderBy('units.name')
            ->select(['units.id', 'units.name', 'units.code', 'properties.name as property_name']);

        TenantContext::applyCurrentPlazaFilter($unitsQuery, 'properties.plaza_id');

        $units = $unitsQuery->get();

        $contracts = collect();

        if ($this->unitId !== null) {
            $contracts = Contract::query()
                ->with('tenant')
                ->where('unit_id', $this->unitId)
                ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
                ->orderByDesc('starts_at')
                ->limit(20)
                ->get();
        }

        return view('livewire.expenses.quick-register-modal', [
            'categories' => $categories,
            'units' => $units,
            'contracts' => $contracts,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'spentAt' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expenseCategoryId' => ['required', 'integer'],
            'unitId' => ['nullable', 'integer'],
            'contractId' => ['nullable', 'integer'],
            'vendor' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'evidenceFile' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'spentAt.required' => __('finance.validation.date_required'),
            'spentAt.date' => __('finance.validation.date_invalid'),
            'amount.required' => __('finance.validation.amount_required'),
            'amount.numeric' => __('finance.validation.amount_numeric'),
            'amount.min' => __('finance.validation.amount_min'),
            'expenseCategoryId.required' => __('finance.validation.category_required'),
            'vendor.max' => __('finance.validation.vendor_max'),
            'notes.max' => __('finance.validation.notes_max'),
            'evidenceFile.max' => __('finance.validation.evidence_max'),
            'evidenceFile.mimes' => __('finance.validation.evidence_mimes'),
        ];
    }
}
