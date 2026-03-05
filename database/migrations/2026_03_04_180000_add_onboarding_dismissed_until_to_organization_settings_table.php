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
        Schema::table('organization_settings', function (Blueprint $table): void {
            $table->timestamp('onboarding_dismissed_until')->nullable()->after('email_template');
            $table->index('onboarding_dismissed_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_settings', function (Blueprint $table): void {
            $table->dropIndex(['onboarding_dismissed_until']);
            $table->dropColumn('onboarding_dismissed_until');
        });
    }
};
