<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('government_id_requirement_ways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requirement_group_id')->constrained('government_id_requirement_groups')->cascadeOnDelete();
            $table->unsignedSmallInteger('required_count')->default(1);
            $table->string('qualification_type', 40)->default('none');
            $table->string('qualification_scope', 20)->default('every');
            $table->text('qualification_custom')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('government_id_requirement_items', function (Blueprint $table) {
            $table->foreignId('requirement_way_id')->nullable()
                ->constrained('government_id_requirement_ways')->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity')->nullable();
        });
        // Existing groups keep their original all/choose_one semantics until edited.
    }

    public function down(): void
    {
        // Older storage cannot express these rules: never silently discard them.
        if (DB::table('government_id_requirement_ways')->exists()
            || DB::table('government_id_requirement_items')->whereNotNull('quantity')->exists()) {
            throw new RuntimeException('Rollback stopped: saved requirement ways or quantities must be exported and migrated before removing this structure.');
        }
        Schema::table('government_id_requirement_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requirement_way_id');
            $table->dropColumn('quantity');
        });
        Schema::dropIfExists('government_id_requirement_ways');
    }
};
