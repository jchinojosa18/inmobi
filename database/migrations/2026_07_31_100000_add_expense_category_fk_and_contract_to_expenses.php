<?php

use App\Models\ExpenseCategory;
use App\Models\Organization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_CATEGORIES = [
        ['name' => 'MANTENIMIENTO', 'is_system' => true],
        ['name' => 'LIMPIEZA', 'is_system' => true],
        ['name' => 'SERVICIO', 'is_system' => true],
        ['name' => 'REEMBOLSO DEPÓSITO', 'is_system' => true],
    ];

    public function up(): void
    {
        Schema::table('expense_categories', function (Blueprint $table): void {
            $table->boolean('is_system')->default(false)->after('is_active');
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->foreignId('expense_category_id')->nullable()->after('unit_id')->constrained('expense_categories');
            $table->foreignId('contract_id')->nullable()->after('expense_category_id')->constrained('contracts');
        });

        Organization::query()->withoutGlobalScopes()->orderBy('id')->each(function (Organization $organization): void {
            $categoryIdsByName = $this->seedCategoriesForOrganization((int) $organization->id);
            $this->backfillExpensesForOrganization((int) $organization->id, $categoryIdsByName);
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'category']);
            $table->dropColumn('category');
            $table->unsignedBigInteger('expense_category_id')->nullable(false)->change();
            $table->index(['organization_id', 'expense_category_id']);
            $table->index(['organization_id', 'contract_id']);
        });
    }

    /** @return array<string, int> */
    private function seedCategoriesForOrganization(int $organizationId): array
    {
        $map = [];

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

            $map[strtoupper($category->name)] = (int) $category->id;
        }

        return $map;
    }

    /** @param array<string, int> $categoryIdsByName */
    private function backfillExpensesForOrganization(int $organizationId, array $categoryIdsByName): void
    {
        $expenses = DB::table('expenses')
            ->where('organization_id', $organizationId)
            ->orderBy('id')
            ->get(['id', 'category', 'meta']);

        foreach ($expenses as $expense) {
            $legacyCategory = strtoupper(trim((string) $expense->category));

            if ($legacyCategory === 'REFUND DEPOSIT') {
                $categoryId = $categoryIdsByName['REEMBOLSO DEPÓSITO'];
            } else {
                $categoryId = $categoryIdsByName[$legacyCategory] ?? null;

                if ($categoryId === null && $legacyCategory !== '') {
                    $created = ExpenseCategory::query()->withoutOrganizationScope()->firstOrCreate(
                        ['organization_id' => $organizationId, 'name' => $legacyCategory],
                        ['is_active' => true, 'is_system' => false],
                    );
                    $categoryId = (int) $created->id;
                    $categoryIdsByName[$legacyCategory] = $categoryId;
                }

                if ($categoryId === null) {
                    $categoryId = $categoryIdsByName['SERVICIO'];
                }
            }

            $meta = json_decode((string) $expense->meta, true);
            $contractId = data_get($meta, 'contract_id');
            $contractId = is_numeric($contractId) ? (int) $contractId : null;

            DB::table('expenses')->where('id', $expense->id)->update([
                'expense_category_id' => $categoryId,
                'contract_id' => $contractId,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->string('category')->nullable();
        });

        $rows = DB::table('expenses')
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->select('expenses.id', 'expense_categories.name')
            ->get();

        foreach ($rows as $row) {
            DB::table('expenses')->where('id', $row->id)->update([
                'category' => $row->name,
            ]);
        }

        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('contract_id');
            $table->dropConstrainedForeignId('expense_category_id');
            $table->index(['organization_id', 'category']);
        });

        Schema::table('expense_categories', function (Blueprint $table): void {
            $table->dropColumn('is_system');
        });
    }
};
