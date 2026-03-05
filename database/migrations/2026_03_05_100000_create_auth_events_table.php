<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('email')->nullable();
            $table->string('event'); // login_success | login_failed | logout
            $table->datetime('occurred_at');
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedBigInteger('plaza_id')->nullable();
            $table->json('meta')->nullable();

            $table->index('occurred_at');
            $table->index('user_id');
            $table->index('organization_id');
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_events');
    }
};
