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
                $table->boolean('include_in_smart_sequencing')->default(true);
            });
        }
    }

    public function down(): void
    {
        foreach (['government_ids', 'documents'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('include_in_smart_sequencing');
            });
        }
    }
};
