<?php

namespace App\Livewire\Expenses;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[On('expense-created')]
    public function onExpenseCreated(): void
    {
        $this->resetPage();
    }

    public ?string $dateFromFilter = null;

    public ?string $dateToFilter = null;

    public ?int $unitFilter = null;

    public string $categoryFilter = '';

    public bool $showForm = false;

    public string $category = '';

    public string $amount = '';

    public string $spent_at = '';

    public ?int $unit_id = null;

    public ?string $vendor = null;

    public ?string $notes = null;

    /**
     * @var array<string, array<string, string>>
     */
    protected $queryString = [
        'dateFromFilter' => ['except' => ''],
        'dateToFilter' => ['except' => ''],
        'unitFilter' => ['except' => ''],
        'categoryFilter' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->spent_at = now()->toDateString();
    }

    public function updatingDateFromFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDateToFilter(): void
    {
        $this->resetPage();
    }

    public function updatingUnitFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules(), $this->messages());

        try {
            Expense::query()->create([
                'organization_id' => auth()->user()?->organization_id,
                'unit_id' => $validated['unit_id'] ?: null,
                'category' => strtoupper(trim($validated['category'])),
                'amount' => $validated['amount'],
                'spent_at' => $validated['spent_at'],
                'vendor' => $validated['vendor'] ?: null,
                'notes' => $validated['notes'] ?: null,
                'meta' => [],
            ]);
        } catch (ValidationException $exception) {
            $message = $exception->errors()['month_close'][0] ?? 'No se pudo registrar el egreso.';
            $this->addError('month_close', $message);

            return;
        }

        session()->flash('success', 'Egreso registrado correctamente.');

        $this->resetForm();
        $this->resetPage();
    }

    public function render(): View
    {
        $unitsQuery = Unit::query()
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->orderBy('units.name')
            ->select(['units.id', 'units.name', 'units.code']);

        TenantContext::applyCurrentPlazaFilter($unitsQuery, 'properties.plaza_id');

        $units = $unitsQuery->get();

        $configuredCategories = ExpenseCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->values();

        $currentPlazaId = TenantContext::currentPlazaId();

        $historicCategories = Expense::query()
            ->when($currentPlazaId !== null, function (Builder $query) use ($currentPlazaId): void {
                $query->whereHas('unit.property', function (Builder $propertyQuery) use ($currentPlazaId): void {
                    $propertyQuery->where('plaza_id', $currentPlazaId);
                });
            })
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->values();

        $categories = $configuredCategories
            ->merge($historicCategories)
            ->filter(fn ($category) => is_string($category) && trim($category) !== '')
            ->unique()
            ->values();

        $expenses = Expense::query()
            ->with(['unit.property'])
            ->when($currentPlazaId !== null, function (Builder $query) use ($currentPlazaId): void {
                $query->whereHas('unit.property', function (Builder $propertyQuery) use ($currentPlazaId): void {
                    $propertyQuery->where('plaza_id', $currentPlazaId);
                });
            })
            ->when($this->dateFromFilter, fn ($query) => $query->whereDate('spent_at', '>=', $this->dateFromFilter))
            ->when($this->dateToFilter, fn ($query) => $query->whereDate('spent_at', '<=', $this->dateToFilter))
            ->when($this->unitFilter, fn ($query) => $query->where('unit_id', $this->unitFilter))
            ->when($this->categoryFilter !== '', fn ($query) => $query->where('category', $this->categoryFilter))
            ->latest('spent_at')
            ->latest('id')
            ->paginate(10);

        return view('livewire.expenses.index', [
            'expenses' => $expenses,
            'units' => $units,
            'categories' => $categories,
        ])->layout('layouts.app', ['title' => 'Egresos']);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'category' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'spent_at' => ['required', 'date'],
            'vendor' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'category.required' => 'La categoría es obligatoria.',
            'category.max' => 'La categoría no debe exceder 100 caracteres.',
            'amount.required' => 'El monto es obligatorio.',
            'amount.numeric' => 'El monto debe ser numérico.',
            'amount.min' => 'El monto debe ser mayor a cero.',
            'spent_at.required' => 'La fecha de gasto es obligatoria.',
            'spent_at.date' => 'La fecha de gasto no es válida.',
            'unit_id.exists' => 'La unidad seleccionada no es válida.',
            'vendor.max' => 'El proveedor no debe exceder 150 caracteres.',
            'notes.max' => 'Las notas no deben exceder 1000 caracteres.',
        ];
    }

    private function resetForm(): void
    {
        $this->reset([
            'unit_id',
            'category',
            'amount',
            'vendor',
            'notes',
        ]);
        $this->spent_at = now()->toDateString();
        $this->showForm = false;
        $this->resetValidation();
    }
}
