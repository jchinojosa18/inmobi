<?php

namespace App\Livewire\Expenses;

use App\Models\Document;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
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

    public string $category = '';

    public string $scope = 'general';

    public ?int $unitId = null;

    public string $unitQuery = '';

    /** @var array<int, array<string, mixed>> */
    public array $unitResults = [];

    public string $vendor = '';

    public string $notes = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $evidenceFile = null;

    #[On('open-quick-expense')]
    public function open(?int $unitId = null): void
    {
        $this->resetForm();
        $this->open = true;

        if ($unitId !== null) {
            $this->scope = 'unit';
            $this->unitId = $unitId;
            $unit = Unit::query()->with('property')->find($unitId);
            if ($unit !== null) {
                $code = $unit->code ? " ({$unit->code})" : '';
                $this->unitQuery = trim("{$unit->property?->name} / {$unit->name}{$code}");
            }
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
        $this->category = '';
        $this->scope = 'general';
        $this->unitId = null;
        $this->unitQuery = '';
        $this->unitResults = [];
        $this->vendor = '';
        $this->notes = '';
        $this->evidenceFile = null;
        $this->resetValidation();
    }

    public function updatedUnitQuery(): void
    {
        $trimmed = trim($this->unitQuery);

        if (mb_strlen($trimmed) < 2) {
            $this->unitResults = [];

            return;
        }

        $term = '%'.$trimmed.'%';

        $this->unitResults = Unit::query()
            ->select(['units.id', 'units.name', 'units.code', 'properties.name as property_name'])
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->whereColumn('properties.organization_id', 'units.organization_id')
            ->where(function ($q) use ($term): void {
                $q->where('units.name', 'like', $term)
                    ->orWhere('units.code', 'like', $term)
                    ->orWhere('properties.name', 'like', $term);
            })
            ->orderBy('properties.name')
            ->orderBy('units.name')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'label' => trim("{$row->property_name} / {$row->name}".($row->code ? " ({$row->code})" : '')),
            ])
            ->all();
    }

    public function updatedScope(): void
    {
        $this->unitId = null;
        $this->unitQuery = '';
        $this->unitResults = [];
        $this->resetValidation(['unitId']);
    }

    public function selectUnit(int $id): void
    {
        $this->unitId = $id;
        $selected = collect($this->unitResults)->firstWhere('id', $id);
        $this->unitQuery = $selected ? (string) $selected['label'] : '';
        $this->unitResults = [];
    }

    public function save(): void
    {
        $this->validate($this->rules(), $this->messages());

        try {
            $expense = Expense::query()->create([
                'organization_id' => auth()->user()?->organization_id,
                'unit_id' => $this->scope === 'unit' ? $this->unitId : null,
                'category' => strtoupper(trim($this->category)),
                'amount' => $this->amount,
                'spent_at' => $this->spentAt,
                'vendor' => $this->vendor ?: null,
                'notes' => $this->notes ?: null,
                'meta' => [],
            ]);
        } catch (ValidationException $e) {
            $this->addError('month_close', $e->errors()['month_close'][0] ?? 'No se pudo registrar el egreso.');

            return;
        }

        if ($this->evidenceFile !== null) {
            $organizationId = (int) auth()->user()?->organization_id;
            $disk = (string) config('filesystems.documents_disk', 'public');
            $path = $this->evidenceFile->store('documents/expenses/'.$organizationId, $disk);

            Document::query()->create([
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

        $this->open = false;
        $this->resetForm();
        $this->dispatch('expense-created');
    }

    public function render(): View
    {
        $configuredCategories = ExpenseCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->values();

        $historicCategories = Expense::query()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->values();

        $categories = $configuredCategories
            ->merge($historicCategories)
            ->filter(fn ($c) => is_string($c) && trim($c) !== '')
            ->unique()
            ->values();

        return view('livewire.expenses.quick-register-modal', [
            'categories' => $categories,
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
            'category' => ['required', 'string', 'max:100'],
            'scope' => ['required', Rule::in(['general', 'unit'])],
            'unitId' => $this->scope === 'unit'
                ? ['required', 'integer', 'exists:units,id']
                : ['nullable', 'integer'],
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
            'spentAt.required' => 'La fecha es obligatoria.',
            'spentAt.date' => 'La fecha no es válida.',
            'amount.required' => 'El monto es obligatorio.',
            'amount.numeric' => 'El monto debe ser numérico.',
            'amount.min' => 'El monto debe ser mayor a cero.',
            'category.required' => 'La categoría es obligatoria.',
            'category.max' => 'La categoría no debe exceder 100 caracteres.',
            'unitId.required' => 'Debes seleccionar una unidad.',
            'unitId.exists' => 'La unidad seleccionada no es válida.',
            'vendor.max' => 'El proveedor no debe exceder 150 caracteres.',
            'notes.max' => 'Las notas no deben exceder 1000 caracteres.',
            'evidenceFile.max' => 'La evidencia no debe exceder 5 MB.',
            'evidenceFile.mimes' => 'La evidencia debe ser JPG, PNG o PDF.',
        ];
    }
}
