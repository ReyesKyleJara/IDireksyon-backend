<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['government_ids', 'documents'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->boolean('is_published')->default(false);
                $table->boolean('requirements_reviewed')->default(false);
            });
        }
        Schema::create('requirement_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('government_id_id')->nullable()->constrained('government_ids')->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('match_rule', 20)->default('all');
            $table->unsignedSmallInteger('minimum_count')->default(1);
            $table->text('condition_notes')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
        Schema::table('requirements', function (Blueprint $table) {
            $table->foreignId('requirement_group_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_dependency')->default(false);
        });
        Schema::create('catalog_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('government_id_id')->nullable()->constrained('government_ids')->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('kind', 20)->default('required');
            $table->string('choice_group')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_fees');
        Schema::table('requirements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requirement_group_id');
            $table->dropColumn('is_dependency');
        });
        Schema::dropIfExists('requirement_groups');
        foreach (['government_ids', 'documents'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['is_published', 'requirements_reviewed']));
        }
    }
};
