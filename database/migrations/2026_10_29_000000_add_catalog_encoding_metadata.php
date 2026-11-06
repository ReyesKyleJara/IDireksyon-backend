<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['government_ids', 'documents'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->json('research_metadata')->nullable());
        }
        foreach (['government_id_requirement_sets', 'document_requirement_sets'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->json('request_channel')->nullable());
        }
    }

    public function down(): void
    {
        $columns = ['government_ids' => 'research_metadata', 'documents' => 'research_metadata',
            'government_id_requirement_sets' => 'request_channel', 'document_requirement_sets' => 'request_channel'];
        // Check every table before altering any table. Never discard encoded research.
        foreach ($columns as $table => $column) {
            if (DB::table($table)->whereNotNull($column)->exists()) {
                throw new RuntimeException('Saved encoding metadata exists. Export and reconcile it before removing these columns.');
            }
        }
        foreach ($columns as $table => $column) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($column));
        }
    }
};
