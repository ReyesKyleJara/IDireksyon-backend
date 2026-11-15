<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A cached, backend-calculated research validation status for CMS filters.
 *
 * Every existing record gets the default 'needs_review'; no existing value is
 * changed and no record becomes 'public_sources' or 'agency_confirmed' here.
 * The status is never accepted from forms and is not part of the public API.
 * (Named to avoid confusion with ReferenceData's development 'verification_status'.)
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['government_ids', 'documents'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('agency_validation_status', 40)->default('needs_review')->index();
            });
        }
    }

    // Drops only the cached status; it can be recalculated from sources and verification rows.
    public function down(): void
    {
        foreach (['government_ids', 'documents'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->dropIndex($name.'_agency_validation_status_index');
                $table->dropColumn('agency_validation_status');
            });
        }
    }
};
