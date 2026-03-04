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
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('contract_id')->constrained('contracts');
            $table->dateTime('paid_at');
            $table->decimal('amount', 12, 2);
            $table->string('method', 20);
            $table->string('reference')->nullable();
            $table->string('receipt_folio');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'contract_id']);
            $table->index(['organization_id', 'paid_at']);
            $table->unique(['organization_id', 'receipt_folio']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
