<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')
                ->constrained('offices')
                ->cascadeOnDelete();

            // 0 = Sunday through 6 = Saturday; unknown does not mean closed.
            $table->unsignedTinyInteger('day_of_week');
            $table->string('status', 20)->default('unknown');
            $table->timestamps();

            $table->unique(['office_id', 'day_of_week']);
        });

        Schema::create('office_schedule_intervals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_schedule_id')
                ->constrained('office_schedules')
                ->cascadeOnDelete();

            // Opening and closing times use Philippine local time.
            $table->time('opens_at');
            $table->time('closes_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_schedule_intervals');
        Schema::dropIfExists('office_schedules');
    }
};
