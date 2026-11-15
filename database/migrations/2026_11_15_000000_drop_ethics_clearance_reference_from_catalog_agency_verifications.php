<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * UREC clearance is part of the team's academic research process, not a CMS feature,
 * so agency verifications no longer store a clearance reference.
 *
 * Only databases that already created the column (from an earlier version of the
 * create migration) are changed. Every verification row and every other column is kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('catalog_agency_verifications', 'ethics_clearance_reference')) {
            Schema::table('catalog_agency_verifications', function (Blueprint $table) {
                $table->dropColumn('ethics_clearance_reference');
            });
        }
    }

    // Nothing to restore: the CMS no longer uses a clearance reference.
    public function down(): void
    {
    }
};
