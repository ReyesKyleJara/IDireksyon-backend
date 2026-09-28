<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('government_ids', function (Blueprint $table) {
            // General information
            $table->text('purpose')->nullable()->after('description');

            // Human-readable prerequisite/dependency explanation.
            // Smart sequencing rules will be stored separately later.
            $table->text('prerequisite_notes')
                ->nullable()
                ->after('requirements');

            // Application guide
            $table->longText('application_process')
                ->nullable()
                ->after('fee');

            $table->longText('renewal_process')
                ->nullable()
                ->after('processing_time');

            $table->longText('replacement_process')
                ->nullable()
                ->after('renewal_process');

            // Office information
            $table->string('office_hours')
                ->nullable()
                ->after('office_location');

            // Verification / official references
            $table->text('official_link')
                ->nullable()
                ->after('office_hours');

            $table->longText('official_sources')
                ->nullable()
                ->after('official_link');

            $table->date('last_verified_at')
                ->nullable()
                ->after('official_sources');
        });
    }

    public function down(): void
    {
        Schema::table('government_ids', function (Blueprint $table) {
            $table->dropColumn([
                'purpose',
                'prerequisite_notes',
                'application_process',
                'renewal_process',
                'replacement_process',
                'office_hours',
                'official_link',
                'official_sources',
                'last_verified_at',
            ]);
        });
    }
};