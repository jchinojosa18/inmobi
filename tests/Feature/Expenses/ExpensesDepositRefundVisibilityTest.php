<?php

namespace Tests\Feature\Expenses;

use App\Actions\Expenses\SeedDefaultExpenseCategoriesAction;
use App\Livewire\Expenses\Index as ExpensesIndex;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExpensesDepositRefundVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_deposit_refund_row_shows_badge_and_contract_link(): void
    {
        [$user, $contract, $expense] = $this->makeRefundExpense();

        Livewire::actingAs($user)
            ->test(ExpensesIndex::class)
            ->assertSee(__('finance.expenses.deposit_refund_badge'))
            ->assertSee(__('finance.expenses.contract_link', ['id' => $contract->id]))
            ->assertSeeHtml(route('contracts.show', $contract));
    }

    public function test_contract_filter_limits_expenses_to_that_contract(): void
    {
        [$user, $contract, $expense] = $this->makeRefundExpense();

        $other = Expense::query()->create([
            'organization_id' => $contract->organization_id,
            'unit_id' => null,
            'contract_id' => null,
            'expense_category_id' => $expense->expense_category_id,
            'spent_at' => '2026-08-05',
            'amount' => 100,
            'notes' => 'otro',
            'meta' => [],
        ]);

        Livewire::actingAs($user)
            ->test(ExpensesIndex::class, ['contractFilter' => (string) $contract->id])
            ->assertSee('$2,500.00')
            ->assertDontSee('$100.00');
    }

    /**
     * @return array{0: User, 1: Contract, 2: Expense}
     */
    private function makeRefundExpense(): array
    {
        $organization = Organization::factory()->create();
        app(SeedDefaultExpenseCategoriesAction::class)->execute((int) $organization->id);

        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $tenant = Tenant::factory()->create(['organization_id' => $organization->id]);
        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => Contract::STATUS_ENDED,
        ]);
        $user = User::factory()->create(['organization_id' => $organization->id]);

        $categoryId = ExpenseCategory::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organization->id)
            ->where('name', 'REEMBOLSO DEPÓSITO')
            ->value('id');

        $expense = Expense::query()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'contract_id' => $contract->id,
            'expense_category_id' => $categoryId,
            'spent_at' => '2026-08-06',
            'amount' => 2500,
            'notes' => 'Devolución de depósito por finiquito',
            'meta' => [
                'reason' => 'contract_settlement',
                'contract_id' => $contract->id,
            ],
        ]);

        $this->actingAs($user);

        return [$user, $contract, $expense];
    }
}
