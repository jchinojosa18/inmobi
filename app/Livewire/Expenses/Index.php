<?php

namespace App\Livewire\Expenses;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public const ASSIGNMENT_ALL = 'all';

    public const ASSIGNMENT_GENERAL = 'general';

    public const ASSIGNMENT_UNIT = 'unit';

    #[On('expense-created')]
    public function onExpenseCreated(): void
    {
        $this->resetPage();
    }

    public ?string $dateFromFilter = null;

    public ?string $dateToFilter = null;

    public string $unitFilter = '';

    public string $categoryFilter = '';

    public string $assignmentFilter = self::ASSIGNMENT_ALL;

    public string $contractFilter = '';

    /**
     * @var array<string, array<string, string>>
     */
    protected $queryString = [
        'dateFromFilter' => ['except' => ''],
        'dateToFilter' => ['except' => ''],
        'unitFilter' => ['except' => ''],
        'categoryFilter' => ['except' => ''],
        'assignmentFilter' => ['except' => self::ASSIGNMENT_ALL],
        'contractFilter' => ['except' => ''],
    ];

    public function mount(): void
    {
        if (! (auth()->user()?->can('expenses.view') ?? false)) {
            abort(403);
        }
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

    public function updatingAssignmentFilter(): void
    {
        $this->resetPage();
    }

    public function updatingContractFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->dateFromFilter = null;
        $this->dateToFilter = null;
        $this->unitFilter = '';
        $this->categoryFilter = '';
        $this->assignmentFilter = self::ASSIGNMENT_ALL;
        $this->contractFilter = '';
        $this->resetPage();
    }

    public function hasActiveFilters(): bool
    {
        return filled($this->dateFromFilter)
            || filled($this->dateToFilter)
            || $this->unitFilter !== ''
            || $this->categoryFilter !== ''
            || $this->assignmentFilter !== self::ASSIGNMENT_ALL
            || $this->contractFilter !== '';
    }

    public function render(): View
    {
        $unitsQuery = Unit::query()
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->orderBy('properties.name')
            ->orderBy('units.name')
            ->select(['units.id', 'units.name', 'units.code', 'properties.name as property_name']);

        TenantContext::applyCurrentPlazaFilter($unitsQuery, 'properties.plaza_id');

        $units = $unitsQuery->get();

        $currentPlazaId = TenantContext::currentPlazaId();

        $scopedExpensesQuery = Expense::query()
            ->when($currentPlazaId !== null, fn (Builder $query) => $this->applyPlazaScope($query, $currentPlazaId))
            ->when($currentPlazaId === null, fn (Builder $query) => $this->applyAssignmentScope($query))
            ->when($this->dateFromFilter, fn ($query) => $query->whereDate('spent_at', '>=', $this->dateFromFilter))
            ->when($this->dateToFilter, fn ($query) => $query->whereDate('spent_at', '<=', $this->dateToFilter))
            ->when($this->unitFilter !== '', fn ($query) => $query->where('unit_id', (int) $this->unitFilter))
            ->when($this->contractFilter !== '', fn ($query) => $query->where('contract_id', (int) $this->contractFilter));

        $categoryIdsInScope = (clone $scopedExpensesQuery)
            ->select('expense_category_id')
            ->distinct()
            ->pluck('expense_category_id')
            ->filter()
            ->values();

        $categoriesQuery = ExpenseCategory::query()->active();

        if ($categoryIdsInScope->isNotEmpty()) {
            $categoriesQuery->whereIn('id', $categoryIdsInScope);
        }

        $categories = $categoriesQuery
            ->orderBy('name')
            ->get(['id', 'name']);

        $expenses = (clone $scopedExpensesQuery)
            ->with(['unit.property', 'expenseCategory', 'contract.tenant'])
            ->when($this->categoryFilter !== '', fn ($query) => $query->where('expense_category_id', (int) $this->categoryFilter))
            ->latest('spent_at')
            ->latest('id')
            ->paginate(10);

        return view('livewire.expenses.index', [
            'expenses' => $expenses,
            'units' => $units,
            'categories' => $categories,
            'canCreateExpenses' => auth()->user()?->can('expenses.create') ?? false,
            'hasActiveFilters' => $this->hasActiveFilters(),
        ])->layout('layouts.app', ['title' => __('finance.expenses.title')]);
    }

    private function applyPlazaScope(Builder $query, int $plazaId): void
    {
        $query->where(function (Builder $scoped) use ($plazaId): void {
            if ($this->assignmentFilter !== self::ASSIGNMENT_UNIT) {
                $scoped->whereNull('unit_id');
            }

            if ($this->assignmentFilter !== self::ASSIGNMENT_GENERAL) {
                $scoped->orWhereHas('unit.property', function (Builder $propertyQuery) use ($plazaId): void {
                    $propertyQuery->where('plaza_id', $plazaId);
                });
            }
        });
    }

    private function applyAssignmentScope(Builder $query): void
    {
        if ($this->assignmentFilter === self::ASSIGNMENT_GENERAL) {
            $query->whereNull('unit_id');

            return;
        }

        if ($this->assignmentFilter === self::ASSIGNMENT_UNIT) {
            $query->whereNotNull('unit_id');
        }
    }
}
