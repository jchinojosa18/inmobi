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
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('id')
                ->constrained('organizations');
        });

        $defaultOrganizationId = DB::table('organizations')
            ->where('name', 'Default')
            ->value('id');

        if ($defaultOrganizationId === null) {
            $defaultOrganizationId = DB::table('organizations')->insertGetId([
                'name' => 'Default',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('users')
            ->whereNull('organization_id')
            ->update(['organization_id' => $defaultOrganizationId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('organization_id');
        });
    }
};
