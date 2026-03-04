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
        Schema::create('charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('contract_id')->constrained('contracts');
            $table->foreignId('unit_id')->constrained('units');
            $table->string('type', 20);
            $table->string('period', 7)->nullable();
            $table->date('charge_date');
            $table->decimal('amount', 12, 2);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'contract_id']);
            $table->index(['organization_id', 'unit_id']);
            $table->index(['organization_id', 'type']);
            $table->index(['organization_id', 'period']);
            $table->index(['organization_id', 'charge_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('charges');
    }
};
