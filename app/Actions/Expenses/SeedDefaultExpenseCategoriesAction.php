<?php

namespace App\Actions\Expenses;

use App\Models\ExpenseCategory;
use RuntimeException;

class SeedDefaultExpenseCategoriesAction
{
    private const DEFAULT_CATEGORIES = [
        ['name' => 'MANTENIMIENTO', 'is_system' => true],
        ['name' => 'LIMPIEZA', 'is_system' => true],
        ['name' => 'SERVICIO', 'is_system' => true],
        ['name' => 'REEMBOLSO DEPÓSITO', 'is_system' => true],
    ];

    public function execute(int $organizationId): void
    {
        foreach (self::DEFAULT_CATEGORIES as $row) {
            $category = ExpenseCategory::query()
                ->withoutOrganizationScope()
                ->firstOrCreate(
                    ['organization_id' => $organizationId, 'name' => $row['name']],
                    ['is_active' => true, 'is_system' => $row['is_system']],
                );

            if (! $category->is_system && $row['is_system']) {
                $category->forceFill(['is_system' => true])->save();
            }
        }
    }

    public function depositRefundCategoryId(int $organizationId): int
    {
        $this->execute($organizationId);

        $categoryId = ExpenseCategory::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->where('name', 'REEMBOLSO DEPÓSITO')
            ->value('id');

        if (! is_numeric($categoryId)) {
            throw new RuntimeException('Missing REEMBOLSO DEPÓSITO expense category for organization '.$organizationId);
        }

        return (int) $categoryId;
    }
}
