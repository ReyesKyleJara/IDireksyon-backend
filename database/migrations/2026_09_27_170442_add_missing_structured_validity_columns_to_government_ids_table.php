<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('government_ids', 'validity_type')) {
            Schema::table('government_ids', function (Blueprint $table) {
                $table->string('validity_type', 30)
                    ->nullable()
                    ->after('validity');
            });
        }

        if (!Schema::hasColumn('government_ids', 'validity_value')) {
            Schema::table('government_ids', function (Blueprint $table) {
                $table->unsignedSmallInteger('validity_value')
                    ->nullable()
                    ->after('validity_type');
            });
        }

        if (!Schema::hasColumn('government_ids', 'validity_unit')) {
            Schema::table('government_ids', function (Blueprint $table) {
                $table->string('validity_unit', 20)
                    ->nullable()
                    ->after('validity_value');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('government_ids', 'validity_unit')) {
            Schema::table('government_ids', function (Blueprint $table) {
                $table->dropColumn('validity_unit');
            });
        }

        if (Schema::hasColumn('government_ids', 'validity_value')) {
            Schema::table('government_ids', function (Blueprint $table) {
                $table->dropColumn('validity_value');
            });
        }

        if (Schema::hasColumn('government_ids', 'validity_type')) {
            Schema::table('government_ids', function (Blueprint $table) {
                $table->dropColumn('validity_type');
            });
        }
    }
};