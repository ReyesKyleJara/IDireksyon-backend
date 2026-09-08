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
        Schema::create('government_ids', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('agency');
            $table->text('purpose')->nullable();
            $table->string('validity')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('last_updated')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('government_ids');
    }
};