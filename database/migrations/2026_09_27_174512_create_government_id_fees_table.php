<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('government_id_fees', function (Blueprint $table) {
            $table->id();

            $table->foreignId('government_id_id')
                ->constrained('government_ids')
                ->cascadeOnDelete();

            $table->string('label');

            $table->string('type', 20)
                ->default('fixed');

            $table->decimal('amount_min', 10, 2)
                ->nullable();

            $table->decimal('amount_max', 10, 2)
                ->nullable();

            $table->string('currency', 10)
                ->default('PHP');

            $table->boolean('is_optional')
                ->default(false);

            $table->text('notes')
                ->nullable();

            $table->unsignedInteger('sort_order')
                ->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('government_id_fees');
    }
};
