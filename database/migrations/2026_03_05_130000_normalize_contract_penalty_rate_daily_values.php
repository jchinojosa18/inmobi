<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contracts')) {
            return;
        }

        DB::table('contracts')
            ->where('penalty_rate_daily', '>', 1)
            ->update([
                'penalty_rate_daily' => DB::raw('(penalty_rate_daily * 1.0) / 100.0'),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Irreversible safely: cannot know which values were originally valid decimals.
    }
};
