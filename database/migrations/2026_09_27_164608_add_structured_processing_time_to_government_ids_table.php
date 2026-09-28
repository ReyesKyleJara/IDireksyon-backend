<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('government_ids', function (Blueprint $table) {
            $table->string('processing_time_type', 30)
                ->nullable()
                ->after('processing_time');

            $table->unsignedSmallInteger('processing_time_min')
                ->nullable()
                ->after('processing_time_type');

            $table->unsignedSmallInteger('processing_time_max')
                ->nullable()
                ->after('processing_time_min');

            $table->string('processing_time_unit', 30)
                ->nullable()
                ->after('processing_time_max');
        });
    }

    public function down(): void
    {
        Schema::table('government_ids', function (Blueprint $table) {
            $table->dropColumn([
                'processing_time_type',
                'processing_time_min',
                'processing_time_max',
                'processing_time_unit',
            ]);
        });
    }
};