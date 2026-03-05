<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('plaza_id')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('action'); // e.g. payment.created, month.closed
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->datetime('occurred_at');
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('summary');
            $table->json('meta')->nullable();

            $table->index('occurred_at');
            $table->index('actor_user_id');
            $table->index('action');
            $table->index('organization_id');
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
