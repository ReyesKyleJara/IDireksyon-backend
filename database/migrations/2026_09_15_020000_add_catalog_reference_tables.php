<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barangays', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('municipality')->default('Santa Maria');
            $table->string('province')->default('Bulacan');
            $table->timestamps();
        });

        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('abbreviation')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('agency_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('barangay_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        foreach (['government_ids', 'documents'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
                $table->string('issuance_level')->nullable();
                $table->string('inventory_key')->nullable()->unique();
                $table->text('research_notes')->nullable();
                $table->string('research_status')->default('draft');
            });
        }

        Schema::create('office_government_id', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->foreignId('government_id')->constrained('government_ids')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['office_id', 'government_id']);
        });

        Schema::create('office_document', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['office_id', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_document');
        Schema::dropIfExists('office_government_id');

        foreach (['government_ids', 'documents'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('agency_id');
                $table->dropConstrainedForeignId('category_id');
                $table->dropUnique(['inventory_key']);
                $table->dropColumn(['issuance_level', 'inventory_key', 'research_notes', 'research_status']);
            });
        }

        Schema::dropIfExists('offices');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('agencies');
        Schema::dropIfExists('barangays');
    }
};
