<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('government_id_prerequisites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('government_id_id')->constrained()->restrictOnDelete();
            $table->foreignId('requirement_set_id')->nullable()->constrained('government_id_requirement_sets', indexName: 'prereq_profile_fk')->restrictOnDelete();
            $table->foreignId('prerequisite_government_id_id')->nullable()->constrained('government_ids', indexName: 'prereq_id_fk')->restrictOnDelete();
            $table->foreignId('prerequisite_document_id')->nullable()->constrained('documents', indexName: 'prereq_document_fk')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('note', 500)->nullable();
            // NULL profiles represent Everyone, but SQL unique indexes normally allow repeated NULLs.
            $table->unsignedBigInteger('profile_scope_id')->storedAs('COALESCE(requirement_set_id, 0)');
            $table->unique(['government_id_id', 'profile_scope_id', 'prerequisite_government_id_id'], 'prereq_unique_id');
            $table->unique(['government_id_id', 'profile_scope_id', 'prerequisite_document_id'], 'prereq_unique_document');
            $table->timestamps();
        });

        $valid = '((prerequisite_government_id_id IS NOT NULL AND prerequisite_document_id IS NULL AND prerequisite_government_id_id <> government_id_id) OR (prerequisite_government_id_id IS NULL AND prerequisite_document_id IS NOT NULL))';
        if (DB::getDriverName() === 'sqlite') {
            // SQLite cannot add a CHECK after CREATE TABLE. Equivalent guards cover both writes.
            foreach (['INSERT', 'UPDATE'] as $operation) {
                $condition = preg_replace('/\b(prerequisite_government_id_id|prerequisite_document_id|government_id_id)\b/', 'NEW.$1', $valid);
                DB::unprepared("CREATE TRIGGER prerequisite_valid_{$operation} BEFORE {$operation} ON government_id_prerequisites BEGIN SELECT RAISE(ABORT, 'Exactly one non-self prerequisite is required') WHERE NOT {$condition}; END");
            }
        } else {
            DB::statement("ALTER TABLE government_id_prerequisites ADD CONSTRAINT prerequisite_valid_target CHECK {$valid}");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('government_id_prerequisites');
    }
};
