<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['government_ids', 'documents'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                // The square image residents see, and the uncropped source kept for re-cropping.
                $table->string('image_path')->nullable();
                $table->string('image_original_path')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['government_ids', 'documents'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['image_path', 'image_original_path']);
            });
        }
    }
};
