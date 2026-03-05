<?php

use App\Models\Plaza;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('properties', 'plaza_id')) {
            Schema::table('properties', function (Blueprint $table): void {
                $table->foreignId('plaza_id')
                    ->nullable()
                    ->after('organization_id');
            });
        }

        $this->backfillPropertyPlazaIds();

        Schema::table('properties', function (Blueprint $table): void {
            $table->foreignId('plaza_id')
                ->nullable(false)
                ->change();

            $table->foreign('plaza_id')
                ->references('id')
                ->on('plazas')
                ->cascadeOnDelete();

            $table->index(['organization_id', 'plaza_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('properties', 'plaza_id')) {
            return;
        }

        Schema::table('properties', function (Blueprint $table): void {
            $table->dropForeign(['plaza_id']);
            $table->dropIndex(['organization_id', 'plaza_id']);
            $table->dropColumn('plaza_id');
        });
    }

    private function backfillPropertyPlazaIds(): void
    {
        $organizationIds = DB::table('organizations')
            ->orderBy('id')
            ->pluck('id');

        foreach ($organizationIds as $organizationId) {
            $defaultPlazaId = $this->ensureDefaultPlazaId((int) $organizationId);

            DB::table('properties')
                ->where('organization_id', (int) $organizationId)
                ->whereNull('plaza_id')
                ->update([
                    'plaza_id' => $defaultPlazaId,
                    'updated_at' => now(),
                ]);
        }

        $missingPlazaCount = DB::table('properties')
            ->whereNull('plaza_id')
            ->count();

        if ($missingPlazaCount > 0) {
            throw new RuntimeException(
                "Backfill incompleto: {$missingPlazaCount} properties quedaron sin plaza_id."
            );
        }
    }

    private function ensureDefaultPlazaId(int $organizationId): int
    {
        $defaultTimezone = trim((string) config('app.timezone'));
        if ($defaultTimezone === '') {
            $defaultTimezone = 'America/Tijuana';
        }

        $defaultPlaza = DB::table('plazas')
            ->where('organization_id', $organizationId)
            ->where('is_default', true)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->first();

        if ($defaultPlaza !== null) {
            return (int) $defaultPlaza->id;
        }

        $principal = DB::table('plazas')
            ->where('organization_id', $organizationId)
            ->where('nombre', Plaza::DEFAULT_NAME)
            ->orderBy('id')
            ->first();

        if ($principal !== null) {
            DB::table('plazas')
                ->where('id', (int) $principal->id)
                ->update([
                    'is_default' => true,
                    'deleted_at' => null,
                    'timezone' => trim((string) $principal->timezone) !== ''
                        ? $principal->timezone
                        : $defaultTimezone,
                    'updated_at' => now(),
                ]);

            DB::table('plazas')
                ->where('organization_id', $organizationId)
                ->where('id', '!=', (int) $principal->id)
                ->where('is_default', true)
                ->update([
                    'is_default' => false,
                    'updated_at' => now(),
                ]);

            return (int) $principal->id;
        }

        $firstPlaza = DB::table('plazas')
            ->where('organization_id', $organizationId)
            ->orderBy('id')
            ->first();

        if ($firstPlaza !== null) {
            DB::table('plazas')
                ->where('id', (int) $firstPlaza->id)
                ->update([
                    'is_default' => true,
                    'deleted_at' => null,
                    'timezone' => trim((string) $firstPlaza->timezone) !== ''
                        ? $firstPlaza->timezone
                        : $defaultTimezone,
                    'updated_at' => now(),
                ]);

            DB::table('plazas')
                ->where('organization_id', $organizationId)
                ->where('id', '!=', (int) $firstPlaza->id)
                ->where('is_default', true)
                ->update([
                    'is_default' => false,
                    'updated_at' => now(),
                ]);

            return (int) $firstPlaza->id;
        }

        return (int) DB::table('plazas')->insertGetId([
            'organization_id' => $organizationId,
            'nombre' => Plaza::DEFAULT_NAME,
            'ciudad' => null,
            'timezone' => $defaultTimezone,
            'is_default' => true,
            'created_by_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
    }
};
