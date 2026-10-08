<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('government_id_requirement_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requirement_set_id')->constrained('government_id_requirement_sets')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('rule', 20);
            $table->string('condition_type', 50)->default('always');
            $table->text('condition_custom')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('government_id_requirement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requirement_group_id')->constrained('government_id_requirement_groups')->cascadeOnDelete();
            $table->string('type', 20);
            $table->foreignId('government_id_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('custom_name')->nullable();
            $table->string('submission_format', 30)->default('not_specified');
            $table->string('submission_format_custom')->nullable();
            $table->unsignedSmallInteger('copies')->nullable();
            $table->text('instructions')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('government_id_requirement_items');
        Schema::dropIfExists('government_id_requirement_groups');
    }
};
