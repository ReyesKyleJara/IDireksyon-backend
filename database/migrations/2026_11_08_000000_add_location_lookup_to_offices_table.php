<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->string('google_place_id')->nullable()->after('longitude');
            $table->string('location_source', 20)->nullable()->after('google_place_id');
            $table->string('location_status', 20)->nullable()->after('location_source');
            $table->dateTime('location_checked_at')->nullable()->after('location_status');
        });

        // Coordinates saved before automatic lookup were typed by staff, so lookups must keep them.
        DB::table('offices')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->update(['location_source' => 'manual']);
    }

    public function down(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->dropColumn(['google_place_id', 'location_source', 'location_status', 'location_checked_at']);
        });
    }
};
