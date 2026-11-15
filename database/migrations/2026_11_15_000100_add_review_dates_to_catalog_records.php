<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * "Last Updated" and "Last Reviewed" for IDs and documents. They are separate on purpose:
 * - content_updated_at: when resident-facing information last changed (set automatically).
 * - last_reviewed_at / last_reviewed_by: when a team member last checked the record against
 *   its sources ("Mark as reviewed"). This is NOT an agency confirmation.
 *
 * Existing records: content_updated_at is filled from the existing change history; nothing is
 * marked reviewed. The older, unused last_verified_at / last_verified_by columns are not touched.
 */
return new class extends Migration
{
    /** Saves that do not change what residents read (same list as App\Services\CatalogReviewDates). */
    private const NOT_INFORMATION = ['image_path', 'image_original_path', 'image', 'agency_validation_status',
        'research_metadata', 'content_updated_at', 'last_reviewed_at', 'last_reviewed_by', 'created_at', 'updated_at'];

    public function up(): void
    {
        foreach (['government_ids', 'documents'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->timestamp('content_updated_at')->nullable();
                $table->timestamp('last_reviewed_at')->nullable();
                $table->foreignId('last_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        foreach (['government_ids' => 'government_id', 'documents' => 'document'] as $name => $entity) {
            DB::table($name)->select(['id', 'created_at', 'updated_at'])->orderBy('id')->each(function ($row) use ($name, $entity) {
                $changed = DB::table('content_change_logs')->where('entity_type', $entity)->where('entity_id', $row->id)
                    ->whereIn('action', ['created', 'updated'])->orderByDesc('created_at')->orderByDesc('id')
                    ->get(['changed_fields', 'created_at'])
                    ->first(fn ($log) => array_diff((array) json_decode((string) $log->changed_fields, true), self::NOT_INFORMATION) !== []);
                $date = $changed->created_at ?? $row->updated_at ?? $row->created_at;
                if ($date !== null) {
                    DB::table($name)->where('id', $row->id)->update(['content_updated_at' => $date]);
                }
            });
        }
    }

    // Removes only the two dates; record information and the change history are untouched.
    public function down(): void
    {
        foreach (['government_ids', 'documents'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('last_reviewed_by');
                $table->dropColumn(['content_updated_at', 'last_reviewed_at']);
            });
        }
    }
};
