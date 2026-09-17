<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('government_ids', function (Blueprint $table) {
            $table->string('level')->nullable()->after('name');
            $table->string('category')->nullable()->after('level');
        });
    }

    public function down(): void
    {
        Schema::table('government_ids', function (Blueprint $table) {
            $table->dropColumn([
                'level',
                'category',
            ]);
        });
    }
};