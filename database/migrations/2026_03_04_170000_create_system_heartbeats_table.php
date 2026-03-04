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
        Schema::create('system_heartbeats', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->timestamp('last_ran_at')->nullable();
            $table->string('status')->default('ok');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_heartbeats');
    }
};
