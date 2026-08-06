<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->index(
                ['organization_id', 'status', 'ends_at'],
                'contracts_organization_status_ends_at_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropIndex('contracts_organization_status_ends_at_index');
        });
    }
};
