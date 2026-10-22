<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resident_roadmap_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('government_id_id')->constrained()->restrictOnDelete();
            $table->string('application_type', 32)->nullable();
            $table->json('profile_selections')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'government_id_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_roadmap_goals');
    }
};
