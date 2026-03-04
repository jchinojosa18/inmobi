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
        Schema::table('properties', function (Blueprint $table): void {
            $table->string('kind', 40)->default('building')->after('status');
            $table->index(['organization_id', 'kind']);
        });

        Schema::table('units', function (Blueprint $table): void {
            $table->string('kind', 40)->default('apartment')->after('status');
            $table->index(['organization_id', 'kind']);
        });

        DB::table('properties')
            ->whereNull('kind')
            ->update(['kind' => 'building']);

        DB::table('units')
            ->whereNull('kind')
            ->update(['kind' => 'apartment']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->dropIndex('units_organization_id_kind_index');
            $table->dropColumn('kind');
        });

        Schema::table('properties', function (Blueprint $table): void {
            $table->dropIndex('properties_organization_id_kind_index');
            $table->dropColumn('kind');
        });
    }
};
