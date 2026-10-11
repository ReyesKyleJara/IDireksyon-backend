<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->text('fee')->nullable()->change();
            $table->foreignId('agency_id')->nullable()->constrained('agencies')->nullOnDelete();
            $table->string('office_hours')->nullable();
            $table->string('eligibility_age_type')->nullable();
            $table->string('eligibility_citizenship')->nullable();
            $table->string('eligibility_residency')->nullable();
            $table->string('eligibility_residency_custom')->nullable();
            $table->string('validity_type')->nullable();
            $table->string('validity_unit')->nullable();
            $table->string('processing_time_type')->nullable();
            $table->string('processing_time_unit')->nullable();
            $table->string('fee_type')->nullable();
            $table->string('fee_currency')->nullable();
            $table->text('purpose')->nullable();
            $table->text('eligibility_other_conditions')->nullable();
            $table->text('prerequisite_notes')->nullable();
            $table->text('application_process')->nullable();
            $table->text('renewal_process')->nullable();
            $table->text('replacement_process')->nullable();
            $table->text('fee_notes')->nullable();
            $table->unsignedSmallInteger('eligibility_min_age')->nullable();
            $table->unsignedSmallInteger('eligibility_max_age')->nullable();
            $table->unsignedSmallInteger('validity_value')->nullable();
            $table->unsignedSmallInteger('processing_time_min')->nullable();
            $table->unsignedSmallInteger('processing_time_max')->nullable();
            $table->decimal('fee_min', 10, 2)->nullable();
            $table->decimal('fee_max', 10, 2)->nullable();
        });

        Schema::create('document_requirement_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('application_type', 30);
            $table->string('application_type_custom')->nullable();
            $table->string('applicant_type', 30);
            $table->string('applicant_type_custom')->nullable();
            $table->unsignedSmallInteger('min_age')->nullable();
            $table->unsignedSmallInteger('max_age')->nullable();
            $table->timestamps();
        });

        Schema::create('document_requirement_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requirement_set_id')->constrained('document_requirement_sets')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('rule', 20);
            $table->string('condition_type', 50)->default('always');
            $table->text('condition_custom')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('document_requirement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requirement_group_id')->constrained('document_requirement_groups')->cascadeOnDelete();
            $table->string('type', 20);
            $table->foreignId('government_id_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('custom_name')->nullable();
            $table->string('submission_format', 30)->default('not_specified');
            $table->string('submission_format_custom')->nullable();
            $table->unsignedSmallInteger('copies')->nullable();
            $table->text('instructions')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('document_requirement_ways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requirement_group_id')->constrained('document_requirement_groups')->cascadeOnDelete();
            $table->unsignedSmallInteger('required_count')->default(1);
            $table->string('qualification_type', 40)->default('none');
            $table->string('qualification_scope', 20)->default('every');
            $table->text('qualification_custom')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('document_requirement_items', function (Blueprint $table) {
            $table->foreignId('requirement_way_id')->nullable()
                ->constrained('document_requirement_ways')->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity')->nullable();
        });

        Schema::create('document_application_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requirement_set_id')->constrained('document_requirement_sets')->restrictOnDelete();
            $table->string('title');
            $table->text('short_description')->nullable();
            $table->string('type', 40)->default('general');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('document_application_step_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_step_id');
            $table->foreign('application_step_id', 'document_guide_blocks_step_foreign')
                ->references('id')->on('document_application_steps')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('section_title')->nullable();
            $table->json('content');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('document_fees', function (Blueprint $table) {
            $table->id();

            $table->foreignId('document_id')
                ->constrained('documents')
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

        Schema::create('document_office', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')
                ->constrained('documents')
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

            $table->unique(['document_id', 'office_id']);
        });
    }

    public function down(): void
    {
        $tables = ['document_application_step_blocks', 'document_application_steps', 'document_requirement_items', 'document_requirement_ways', 'document_requirement_groups', 'document_requirement_sets', 'document_fees', 'document_office'];
        $columns = ['agency_id', 'office_hours', 'eligibility_age_type', 'eligibility_citizenship', 'eligibility_residency', 'eligibility_residency_custom', 'validity_type', 'validity_unit', 'processing_time_type', 'processing_time_unit', 'fee_type', 'fee_currency', 'purpose', 'eligibility_other_conditions', 'prerequisite_notes', 'application_process', 'renewal_process', 'replacement_process', 'fee_notes', 'eligibility_min_age', 'eligibility_max_age', 'validity_value', 'processing_time_min', 'processing_time_max', 'fee_min', 'fee_max'];
        // Stop before any DDL if rollback would discard new research.
        foreach ($tables as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException('Rollback stopped: export or migrate document research before removing its storage.');
            }
        }
        foreach ($columns as $column) {
            if (DB::table('documents')->whereNotNull($column)->exists()) {
                throw new RuntimeException('Rollback stopped: documents contain saved structured details.');
            }
        }
        if (DB::table('documents')->whereRaw('LENGTH(fee) > 255')->exists()) {
            throw new RuntimeException('Rollback stopped: a document fee summary exceeds the old column capacity.');
        }
        foreach ($tables as $table) Schema::dropIfExists($table);
        Schema::table('documents', function (Blueprint $table) use ($columns) {
            $table->string('fee')->nullable()->change();
            $table->dropConstrainedForeignId('agency_id');
            $table->dropColumn(array_values(array_diff($columns, ['agency_id'])));
        });
    }
};
