<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resident_journeys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('journey_key', 64);
            $table->json('plan');
            $table->timestamps();
            $table->unique(['user_id', 'journey_key']);
        });
        Schema::create('resident_journey_completions', function (Blueprint $table) {
            $table->foreignId('resident_journey_id')->constrained()->cascadeOnDelete();
            $table->string('step_identity', 255);
            $table->dateTime('completed_at');
            $table->primary(['resident_journey_id', 'step_identity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_journey_completions');
        Schema::dropIfExists('resident_journeys');
    }
};
