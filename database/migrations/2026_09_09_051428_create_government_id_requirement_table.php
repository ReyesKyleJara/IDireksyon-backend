<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('government_id_requirement', function (Blueprint $table) {
            $table->id();

            $table->foreignId('government_id')
                ->constrained('government_ids')
                ->cascadeOnDelete();

            $table->foreignId('requirement_id')
                ->constrained('requirements')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['government_id', 'requirement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('government_id_requirement');
    }
};