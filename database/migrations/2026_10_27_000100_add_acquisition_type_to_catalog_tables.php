<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['government_ids', 'documents'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('acquisition_type')->default('general_public');
            });
        }
    }

    public function down(): void
    {
        foreach (['government_ids', 'documents'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('acquisition_type');
            });
        }
    }
};
