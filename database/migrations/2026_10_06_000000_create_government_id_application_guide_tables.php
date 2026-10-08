<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('government_id_application_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requirement_set_id')->constrained('government_id_requirement_sets')->restrictOnDelete();
            $table->string('title');
            $table->text('short_description')->nullable();
            $table->string('type', 40)->default('general');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('government_id_application_step_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_step_id');
            $table->foreign('application_step_id', 'guide_blocks_step_foreign')
                ->references('id')->on('government_id_application_steps')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('section_title')->nullable();
            $table->json('content');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('government_id_application_steps')->exists()) {
            throw new RuntimeException('Rollback stopped: application guides contain saved steps. Export or migrate them before removing these tables.');
        }
        Schema::dropIfExists('government_id_application_step_blocks');
        Schema::dropIfExists('government_id_application_steps');
    }
};
