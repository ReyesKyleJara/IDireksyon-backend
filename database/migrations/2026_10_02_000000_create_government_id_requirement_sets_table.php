<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('government_id_requirement_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('government_id_id')->constrained()->cascadeOnDelete();
            $table->string('application_type', 30);
            $table->string('application_type_custom')->nullable();
            $table->string('applicant_type', 30);
            $table->string('applicant_type_custom')->nullable();
            $table->unsignedSmallInteger('min_age')->nullable();
            $table->unsignedSmallInteger('max_age')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('government_id_requirement_sets');
    }
};
