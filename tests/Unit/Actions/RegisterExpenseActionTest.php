<?php

namespace Tests\Unit\Actions;

use App\Actions\Expenses\RegisterExpenseAction;
use App\Actions\Expenses\SeedDefaultExpenseCategoriesAction;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RegisterExpenseActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_expense_with_active_category(): void
    {
        $organization = Organization::factory()->create();
        app(SeedDefaultExpenseCategoriesAction::class)->execute($organization->id);
        $categoryId = ExpenseCategory::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organization->id)
            ->where('name', 'MANTENIMIENTO')
            ->value('id');

        $expense = app(RegisterExpenseAction::class)->execute($organization->id, [
            'expense_category_id' => $categoryId,
            'amount' => 150.50,
            'spent_at' => '2026-07-15',
            'vendor' => 'Proveedor SA',
        ]);

        $this->assertInstanceOf(Expense::class, $expense);
        $this->assertSame((int) $categoryId, $expense->expense_category_id);
        $this->assertNull($expense->unit_id);
        $this->assertNull($expense->contract_id);
    }

    public function test_it_rejects_category_from_another_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();
        app(SeedDefaultExpenseCategoriesAction::class)->execute($organizationA->id);
        $foreignCategoryId = ExpenseCategory::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationA->id)
            ->value('id');

        $this->expectException(ValidationException::class);

        app(RegisterExpenseAction::class)->execute($organizationB->id, [
            'expense_category_id' => $foreignCategoryId,
            'amount' => 100,
            'spent_at' => '2026-07-15',
        ]);
    }

    public function test_it_rejects_inactive_category(): void
    {
        $organization = Organization::factory()->create();
        $category = ExpenseCategory::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => false,
        ]);

        $this->expectException(ValidationException::class);

        app(RegisterExpenseAction::class)->execute($organization->id, [
            'expense_category_id' => $category->id,
            'amount' => 100,
            'spent_at' => '2026-07-15',
        ]);
    }

    public function test_it_rejects_contract_when_unit_does_not_match(): void
    {
        $organization = Organization::factory()->create();
        app(SeedDefaultExpenseCategoriesAction::class)->execute($organization->id);
        $categoryId = ExpenseCategory::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organization->id)
            ->value('id');

        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unitA = Unit::factory()->create(['organization_id' => $organization->id, 'property_id' => $property->id]);
        $unitB = Unit::factory()->create(['organization_id' => $organization->id, 'property_id' => $property->id]);
        $tenant = Tenant::factory()->create(['organization_id' => $organization->id]);
        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unitA->id,
            'tenant_id' => $tenant->id,
        ]);

        $this->expectException(ValidationException::class);

        app(RegisterExpenseAction::class)->execute($organization->id, [
            'expense_category_id' => $categoryId,
            'amount' => 100,
            'spent_at' => '2026-07-15',
            'unit_id' => $unitB->id,
            'contract_id' => $contract->id,
        ]);
    }

    public function test_it_allows_general_expense_without_unit_or_contract(): void
    {
        $organization = Organization::factory()->create();
        app(SeedDefaultExpenseCategoriesAction::class)->execute($organization->id);
        $categoryId = ExpenseCategory::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organization->id)
            ->where('name', 'SERVICIO')
            ->value('id');

        $expense = app(RegisterExpenseAction::class)->execute($organization->id, [
            'expense_category_id' => $categoryId,
            'amount' => 80,
            'spent_at' => '2026-07-20',
        ]);

        $this->assertNull($expense->unit_id);
        $this->assertNull($expense->contract_id);
    }
}
