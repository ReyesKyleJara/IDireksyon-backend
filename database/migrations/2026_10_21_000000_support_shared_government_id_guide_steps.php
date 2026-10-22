<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // SQLite foreign-key pragmas must run outside a transaction during table rebuilds.
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::withoutForeignKeyConstraints(function () {
            Schema::table('government_id_application_steps', function (Blueprint $table) {
                $table->foreignId('government_id_id')->nullable()->constrained('government_ids')->restrictOnDelete();
                $table->unsignedBigInteger('requirement_set_id')->nullable()->change();
            });
        });
        // Preserve IDs, profile assignments, timestamps, order and block content.
        DB::table('government_id_application_steps')->orderBy('id')->each(function ($step) {
            $owner = DB::table('government_id_requirement_sets')->where('id', $step->requirement_set_id)->value('government_id_id');
            DB::table('government_id_application_steps')->where('id', $step->id)->update(['government_id_id' => $owner]);
        });
    }

    public function down(): void
    {
        if (DB::table('government_id_application_steps')->whereNull('requirement_set_id')->exists()) {
            throw new RuntimeException('Rollback stopped: shared guide steps must be explicitly assigned to profiles first.');
        }
        Schema::withoutForeignKeyConstraints(function () {
            Schema::table('government_id_application_steps', function (Blueprint $table) {
                $table->unsignedBigInteger('requirement_set_id')->nullable(false)->change();
                $table->dropConstrainedForeignId('government_id_id');
            });
        });
    }
};
