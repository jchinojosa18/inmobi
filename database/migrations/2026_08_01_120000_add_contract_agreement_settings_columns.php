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
            $table->string('landlord_name')->nullable()->after('email_template');
            $table->string('landlord_rep')->nullable()->after('landlord_name');
            $table->text('contract_email_template')->nullable()->after('landlord_rep');
            $table->text('contract_whatsapp_template')->nullable()->after('contract_email_template');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'landlord_name',
                'landlord_rep',
                'contract_email_template',
                'contract_whatsapp_template',
            ]);
        });
    }
};
