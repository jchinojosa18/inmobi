<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('unit_id')->constrained('units');
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->decimal('rent_amount', 12, 2);
            $table->decimal('deposit_amount', 12, 2)->default(0);
            $table->unsignedTinyInteger('due_day');
            $table->unsignedTinyInteger('grace_days')->default(5);
            $table->decimal('penalty_rate_daily', 8, 4)->default(0);
            $table->string('status', 20)->default('active');
            $table->unsignedTinyInteger('active_lock')->nullable()->default(1);
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'unit_id']);
            $table->index(['organization_id', 'tenant_id']);
            $table->index(['organization_id', 'starts_at']);
            $table->unique(['unit_id', 'active_lock']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
