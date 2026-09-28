<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('government_ids', function (Blueprint $table) {
            $table->foreignId('agency_id')
                ->nullable()
                ->after('category')
                ->constrained('agencies')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('government_ids', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agency_id');
        });
    }
};