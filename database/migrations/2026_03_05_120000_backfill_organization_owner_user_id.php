<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OWNER_INDEX = 'organizations_owner_user_id_idx';

    public function up(): void
    {
        if (! Schema::hasTable('organizations') || ! Schema::hasColumn('organizations', 'owner_user_id')) {
            return;
        }

        $this->ensureOwnerIndex();
        $this->backfillOwnerUsers();
    }

    public function down(): void
    {
        if (! Schema::hasTable('organizations')) {
            return;
        }

        if ($this->indexExistsByName('organizations', self::OWNER_INDEX)) {
            Schema::table('organizations', function (Blueprint $table): void {
                $table->dropIndex(self::OWNER_INDEX);
            });
        }
    }

    private function backfillOwnerUsers(): void
    {
        $organizationIds = DB::table('organizations')
            ->whereNull('owner_user_id')
            ->orderBy('id')
            ->pluck('id');

        foreach ($organizationIds as $organizationId) {
            $ownerUserId = $this->resolveOwnerUserId((int) $organizationId);
            if ($ownerUserId === null) {
                continue;
            }

            DB::table('organizations')
                ->where('id', (int) $organizationId)
                ->whereNull('owner_user_id')
                ->update([
                    'owner_user_id' => $ownerUserId,
                    'updated_at' => now(),
                ]);
        }
    }

    private function resolveOwnerUserId(int $organizationId): ?int
    {
        $adminRoleIds = DB::table('roles')
            ->where('name', 'Admin')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($adminRoleIds !== []) {
            $adminUserId = DB::table('users')
                ->join('model_has_roles', function ($join) use ($adminRoleIds): void {
                    $join->on('model_has_roles.model_id', '=', 'users.id')
                        ->where('model_has_roles.model_type', User::class)
                        ->whereIn('model_has_roles.role_id', $adminRoleIds);
                })
                ->where('users.organization_id', $organizationId)
                ->orderBy('users.created_at')
                ->orderBy('users.id')
                ->value('users.id');

            if ($adminUserId !== null) {
                return (int) $adminUserId;
            }
        }

        $oldestUserId = DB::table('users')
            ->where('organization_id', $organizationId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->value('id');

        return $oldestUserId !== null ? (int) $oldestUserId : null;
    }

    private function ensureOwnerIndex(): void
    {
        if ($this->hasIndexOnColumn('organizations', 'owner_user_id')) {
            return;
        }

        Schema::table('organizations', function (Blueprint $table): void {
            $table->index('owner_user_id', self::OWNER_INDEX);
        });
    }

    private function hasIndexOnColumn(string $table, string $column): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            return (int) DB::table('information_schema.statistics')
                ->whereRaw('table_schema = database()')
                ->where('table_name', $table)
                ->where('column_name', $column)
                ->count() > 0;
        }

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");

            foreach ($indexes as $index) {
                $indexName = (string) ($index->name ?? '');
                if ($indexName === '') {
                    continue;
                }

                $columns = DB::select("PRAGMA index_info('{$indexName}')");
                foreach ($columns as $indexedColumn) {
                    if ((string) ($indexedColumn->name ?? '') === $column) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function indexExistsByName(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            return (int) DB::table('information_schema.statistics')
                ->whereRaw('table_schema = database()')
                ->where('table_name', $table)
                ->where('index_name', $indexName)
                ->count() > 0;
        }

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");
            foreach ($indexes as $index) {
                if ((string) ($index->name ?? '') === $indexName) {
                    return true;
                }
            }
        }

        return false;
    }
};
