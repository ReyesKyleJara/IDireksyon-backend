<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('agency')->nullable();
            $table->text('purpose')->nullable();
            $table->string('validity')->nullable();
            $table->timestamp('last_updated')->nullable();
        });

        foreach (['government_ids', 'documents'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->json('application_steps')->nullable();
                $table->text('cost_notes')->nullable();
                $table->text('source_url')->nullable();
                $table->date('source_checked_at')->nullable();
            });
        }

        Schema::create('document_requirement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['document_id', 'requirement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_requirement');
        foreach (['government_ids', 'documents'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['application_steps', 'cost_notes', 'source_url', 'source_checked_at']);
            });
        }
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['agency', 'purpose', 'validity', 'last_updated']);
        });
    }
};
