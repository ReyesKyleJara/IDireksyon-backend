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
                $table->text('processing_time_note')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['government_ids', 'documents'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('processing_time_note');
            });
        }
    }
};
