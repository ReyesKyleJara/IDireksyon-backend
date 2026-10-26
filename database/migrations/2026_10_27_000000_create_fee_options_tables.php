<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['government_id', 'document'] as $kind) {
            Schema::create($kind.'_fee_options', function (Blueprint $table) use ($kind) {
                $table->id();
                $table->foreignId($kind.'_fee_id')->constrained($kind.'_fees')->cascadeOnDelete();
                $table->string('label');
                $table->string('type', 20)->default('fixed');
                $table->decimal('amount_min', 10, 2)->nullable();
                $table->decimal('amount_max', 10, 2)->nullable();
                $table->string('currency', 10)->default('PHP');
                $table->text('description')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Check both tables before dropping either; rollback must not erase research.
        foreach (['government_id_fee_options', 'document_fee_options'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Rollback stopped: saved fee options must be exported or deliberately removed first.');
            }
        }
        foreach (['government_id_fees', 'document_fees'] as $table) {
            if (DB::table($table)->where('type', 'options')->exists()) {
                throw new RuntimeException('Rollback stopped: convert option fees to simple fees before removing option storage.');
            }
        }
        Schema::dropIfExists('document_fee_options');
        Schema::dropIfExists('government_id_fee_options');
    }
};
