<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->string('category', 50)->nullable()->after('type');

            $table->unique(
                ['organization_id', 'documentable_type', 'documentable_id', 'category'],
                'uniq_docs_org_docable_category'
            );
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropUnique('uniq_docs_org_docable_category');
            $table->dropColumn('category');
        });
    }
};
