<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('ine_clave', 18)->nullable()->after('phone');
            $table->unique(['organization_id', 'ine_clave']);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropUnique(['organization_id', 'ine_clave']);
            $table->dropColumn('ine_clave');
        });
    }
};
