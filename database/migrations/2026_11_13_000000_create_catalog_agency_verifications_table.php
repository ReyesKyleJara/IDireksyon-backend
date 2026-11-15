<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Direct agency confirmations for Government IDs and Documents.
 *
 * Kept apart from research_metadata.sources: a public website reference is not
 * an agency confirmation. Rows count only after a different super_admin approves them.
 * Evidence is stored by reference only; no files are kept here.
 *
 * The table starts empty. No existing record is changed or confirmed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_agency_verifications', function (Blueprint $table) {
            $table->id();

            // The GovernmentId or Document being confirmed. Deletion of a record
            // with history is blocked in application code (no database FK here).
            // Explicit name: the generated one is exactly MySQL's 64-character limit.
            $table->morphs('verifiable', 'agency_verifications_verifiable_index');

            // Agency and office consulted. Names are copied so history stays readable.
            $table->foreignId('agency_id')->nullable()->constrained('agencies')->restrictOnDelete();
            $table->string('agency_name');
            $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->string('office_label')->nullable();

            // How and when the agency was contacted.
            $table->string('method', 40);
            $table->date('requested_on')->nullable();
            $table->date('responded_on')->nullable();

            // What the agency said, limited to an exact documented scope.
            $table->string('outcome', 40)->default('pending');
            $table->json('confirmed_aspects');
            $table->json('not_covered_aspects')->nullable();
            $table->json('scope_request_paths')->nullable(); // null = every request path
            $table->text('scope');
            $table->text('not_covered_note')->nullable();
            $table->text('corrections_summary')->nullable();
            $table->timestamp('corrections_applied_at')->nullable();
            $table->foreignId('corrections_applied_by')->nullable()->constrained('users')->restrictOnDelete();

            // Copy of the confirmed information at approval; any later change invalidates it.
            $table->json('content_snapshot')->nullable();
            $table->char('content_fingerprint', 64)->nullable();
            $table->unsignedSmallInteger('fingerprint_version')->nullable();

            // Private research details: never sent to residents or the API.
            $table->string('evidence_reference')->nullable();
            $table->string('evidence_location')->nullable();
            $table->string('contact_role')->nullable(); // role only, never a personal name
            $table->text('notes')->nullable();

            // Two-person workflow: recorded by one CMS user, approved by a different super_admin.
            $table->string('review_state', 20)->default('draft')->index();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('recorded_by_name');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('returned_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('approved_by_name')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('revocation_reason')->nullable();

            $table->timestamps();
        });
    }

    /*
     * Drops the table and every verification row in it.
     * Export the table before rolling back. Never roll back in production once
     * real agency verifications have been recorded: that history would be lost.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_agency_verifications');
    }
};
