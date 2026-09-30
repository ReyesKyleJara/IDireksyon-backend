<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('government_ids', function (Blueprint $table) {
            $table->string('eligibility_age_type', 20)->nullable()->default(null);
            $table->unsignedSmallInteger('eligibility_min_age')->nullable()->default(null);
            $table->unsignedSmallInteger('eligibility_max_age')->nullable()->default(null);
            $table->string('eligibility_citizenship', 30)->nullable()->default(null);
            $table->string('eligibility_residency', 30)->nullable()->default(null);
            $table->string('eligibility_residency_custom', 255)->nullable()->default(null);
            $table->text('eligibility_other_conditions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('government_ids', function (Blueprint $table) {
            $table->dropColumn([
                'eligibility_age_type',
                'eligibility_min_age',
                'eligibility_max_age',
                'eligibility_citizenship',
                'eligibility_residency',
                'eligibility_residency_custom',
                'eligibility_other_conditions',
            ]);
        });
    }
};
