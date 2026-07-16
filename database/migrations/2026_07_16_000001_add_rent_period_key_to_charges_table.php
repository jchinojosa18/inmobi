<?php

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
        $this->cleanupDuplicateRentCharges();

        Schema::table('charges', function (Blueprint $table): void {
            $table->string('rent_period_key', 7)->nullable()->after('period');
        });

        DB::table('charges')
            ->where('type', 'RENT')
            ->whereNull('deleted_at')
            ->update(['rent_period_key' => DB::raw('period')]);

        Schema::table('charges', function (Blueprint $table): void {
            $table->unique(['contract_id', 'rent_period_key'], 'charges_contract_rent_period_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table): void {
            $table->dropUnique('charges_contract_rent_period_key_unique');
        });

        Schema::table('charges', function (Blueprint $table): void {
            $table->dropColumn('rent_period_key');
        });
    }

    /**
     * Duplicate RENT charges for the same (contract_id, period) cannot coexist
     * once the unique index is added. Duplicates without payment allocations
     * are safe to soft-delete (keeping the oldest row); duplicates that do
     * have allocations require manual review, so we abort instead of losing
     * ledger data.
     */
    private function cleanupDuplicateRentCharges(): void
    {
        $duplicateGroups = DB::table('charges')
            ->select('contract_id', 'period')
            ->where('type', 'RENT')
            ->whereNull('deleted_at')
            ->groupBy('contract_id', 'period')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateGroups as $group) {
            $rows = DB::table('charges')
                ->where('type', 'RENT')
                ->where('contract_id', $group->contract_id)
                ->where('period', $group->period)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get(['id']);

            $keepId = $rows->first()->id;
            $duplicateIds = $rows->pluck('id')->reject(fn ($id) => $id === $keepId)->values();

            if ($duplicateIds->isEmpty()) {
                continue;
            }

            $hasAllocations = DB::table('payment_allocations')
                ->whereIn('charge_id', $duplicateIds)
                ->whereNull('deleted_at')
                ->exists();

            if ($hasAllocations) {
                throw new \RuntimeException(sprintf(
                    'Cannot add rent_period_key unique index: duplicate RENT charges with existing payment allocations found for contract_id=%d, period=%s. Resolve manually before re-running this migration.',
                    $group->contract_id,
                    $group->period
                ));
            }

            DB::table('charges')
                ->whereIn('id', $duplicateIds)
                ->update(['deleted_at' => now()]);
        }
    }
};
