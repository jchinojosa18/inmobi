<?php

namespace Tests\Feature\Expenses;

use App\Actions\Expenses\SeedDefaultExpenseCategoriesAction;
use App\Livewire\Expenses\Index;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExpensesIndexFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_filter_limits_results(): void
    {
        $user = User::factory()->create();
        $organizationId = (int) $user->organization_id;
        app(SeedDefaultExpenseCategoriesAction::class)->execute($organizationId);

        $maintenanceId = ExpenseCategory::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->where('name', 'MANTENIMIENTO')
            ->value('id');
        $serviceId = ExpenseCategory::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->where('name', 'SERVICIO')
            ->value('id');

        Expense::factory()->create([
            'organization_id' => $organizationId,
            'expense_category_id' => $maintenanceId,
            'unit_id' => null,
            'amount' => 100,
            'spent_at' => '2026-07-01',
            'vendor' => 'Proveedor Mantenimiento',
        ]);
        Expense::factory()->create([
            'organization_id' => $organizationId,
            'expense_category_id' => $serviceId,
            'unit_id' => null,
            'amount' => 200,
            'spent_at' => '2026-07-02',
            'vendor' => 'Proveedor Servicio',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set('categoryFilter', (string) $maintenanceId)
            ->assertSee('Proveedor Mantenimiento')
            ->assertDontSee('Proveedor Servicio');
    }

    public function test_assignment_filter_shows_only_general_expenses(): void
    {
        $user = User::factory()->create();
        $organizationId = (int) $user->organization_id;

        $property = Property::factory()->create(['organization_id' => $organizationId, 'name' => 'Torre Norte']);
        $unit = Unit::factory()->create([
            'organization_id' => $organizationId,
            'property_id' => $property->id,
            'name' => 'Local 2',
        ]);

        Expense::factory()->general()->create([
            'organization_id' => $organizationId,
            'vendor' => 'Proveedor General Único',
            'spent_at' => '2026-07-10',
        ]);
        Expense::factory()->create([
            'organization_id' => $organizationId,
            'unit_id' => $unit->id,
            'vendor' => 'Proveedor Unidad Único',
            'spent_at' => '2026-07-11',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set('assignmentFilter', 'general')
            ->assertSee('Proveedor General Único')
            ->assertDontSee('Proveedor Unidad Único');
    }

    public function test_active_categories_appear_in_filter_even_without_expenses(): void
    {
        $user = User::factory()->create();
        app(SeedDefaultExpenseCategoriesAction::class)->execute((int) $user->organization_id);

        $this->actingAs($user)
            ->get(route('expenses.index'))
            ->assertOk()
            ->assertSee('MANTENIMIENTO')
            ->assertSee('LIMPIEZA')
            ->assertSee('SERVICIO');
    }
}
