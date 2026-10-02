<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('government_id_office', function (Blueprint $table) {
            $table->id();
            $table->foreignId('government_id_id')
                ->constrained('government_ids')
                ->cascadeOnDelete();
            $table->foreignId('office_id')
                ->constrained('offices')
                ->cascadeOnDelete();

            // Unverified services must not be assumed available or unavailable.
            $table->string('new_application_status', 20)->default('unknown');
            $table->string('renewal_status', 20)->default('unknown');
            $table->string('replacement_status', 20)->default('unknown');
            $table->text('service_notes')->nullable();

            $table->string('source_url', 2048)->nullable();
            $table->dateTime('last_verified_at')->nullable();
            $table->foreignId('last_verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['government_id_id', 'office_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('government_id_office');
    }
};
