<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prevent hard-deleting a plaza from cascading into properties.
     * Soft-delete of plazas does not fire MySQL ON DELETE CASCADE, but
     * forceDelete / raw deletes previously would wipe properties.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('properties', 'plaza_id')) {
            return;
        }

        Schema::table('properties', function (Blueprint $table): void {
            $table->dropForeign(['plaza_id']);
        });

        Schema::table('properties', function (Blueprint $table): void {
            $table->foreign('plaza_id')
                ->references('id')
                ->on('plazas')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('properties', 'plaza_id')) {
            return;
        }

        Schema::table('properties', function (Blueprint $table): void {
            $table->dropForeign(['plaza_id']);
        });

        Schema::table('properties', function (Blueprint $table): void {
            $table->foreign('plaza_id')
                ->references('id')
                ->on('plazas')
                ->cascadeOnDelete();
        });
    }
};
