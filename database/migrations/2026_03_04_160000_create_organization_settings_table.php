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
        Schema::create('organization_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_folio_mode')->default('annual');
            $table->string('receipt_folio_prefix')->nullable();
            $table->unsignedTinyInteger('receipt_folio_padding')->default(6);
            $table->unsignedTinyInteger('penalty_rounding_scale')->default(2);
            $table->text('penalty_calculation_policy')->nullable();
            $table->text('whatsapp_template')->nullable();
            $table->text('email_template')->nullable();
            $table->timestamps();

            $table->unique('organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_settings');
    }
};
