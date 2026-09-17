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
        $table->string('issued_by');
        $table->string('office_location')->nullable();

        $table->text('description')->nullable();
        $table->text('eligibility')->nullable();
        $table->text('requirements')->nullable();

        $table->string('fee')->nullable();
        $table->string('processing_time')->nullable();
        $table->string('validity')->nullable();

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
