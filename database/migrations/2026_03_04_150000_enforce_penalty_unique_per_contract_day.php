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
        DB::table('charges')
            ->where('type', '!=', 'PENALTY')
            ->whereNotNull('penalty_date')
            ->update(['penalty_date' => null]);

        $duplicates = DB::table('charges')
            ->selectRaw('contract_id, penalty_date, type, MIN(id) as keep_id, COUNT(*) as total')
            ->where('type', 'PENALTY')
            ->whereNotNull('penalty_date')
            ->groupBy('contract_id', 'penalty_date', 'type')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('charges')
                ->where('contract_id', $duplicate->contract_id)
                ->where('penalty_date', $duplicate->penalty_date)
                ->where('type', $duplicate->type)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('charges', function (Blueprint $table): void {
            $table->unique(
                ['contract_id', 'penalty_date', 'type'],
                'charges_contract_penalty_type_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table): void {
            $table->dropUnique('charges_contract_penalty_type_unique');
        });
    }
};
