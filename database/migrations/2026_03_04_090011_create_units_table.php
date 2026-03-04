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
        Schema::create('units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('property_id')->constrained('properties');
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('floor')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'property_id']);
            $table->index(['organization_id', 'status']);
            $table->unique(['property_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
