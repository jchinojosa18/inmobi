<?php

use App\Support\TextCase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tenants')
            ->orderBy('id')
            ->chunkById(200, function ($tenants): void {
                foreach ($tenants as $tenant) {
                    $upper = TextCase::upper($tenant->full_name);

                    if ($upper === null || $upper === $tenant->full_name) {
                        continue;
                    }

                    DB::table('tenants')
                        ->where('id', $tenant->id)
                        ->update([
                            'full_name' => $upper,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Irreversible: original casing is not retained.
    }
};
