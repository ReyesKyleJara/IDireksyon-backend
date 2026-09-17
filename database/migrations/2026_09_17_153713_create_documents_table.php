<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            $table->string('level')->nullable();
            $table->string('category')->nullable();

            $table->string('issued_by')->nullable();
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

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};