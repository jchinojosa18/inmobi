<?php

namespace Tests\Unit\Actions;

use App\Actions\Expenses\SeedDefaultExpenseCategoriesAction;
use App\Models\ExpenseCategory;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedDefaultExpenseCategoriesActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_four_system_categories_idempotently(): void
    {
        $organization = Organization::factory()->create();
        $action = app(SeedDefaultExpenseCategoriesAction::class);

        $action->execute($organization->id);
        $action->execute($organization->id);

        $this->assertSame(4, ExpenseCategory::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organization->id)
            ->count());
        $this->assertTrue(
            (bool) ExpenseCategory::query()
                ->withoutOrganizationScope()
                ->where('organization_id', $organization->id)
                ->where('name', 'REEMBOLSO DEPÓSITO')
                ->value('is_system')
        );
    }

    public function test_it_returns_deposit_refund_category_id(): void
    {
        $organization = Organization::factory()->create();
        $action = app(SeedDefaultExpenseCategoriesAction::class);
        $action->execute($organization->id);

        $id = $action->depositRefundCategoryId($organization->id);

        $this->assertSame(
            ExpenseCategory::query()
                ->withoutOrganizationScope()
                ->where('organization_id', $organization->id)
                ->where('name', 'REEMBOLSO DEPÓSITO')
                ->value('id'),
            $id
        );
    }
}
