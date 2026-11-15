<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Hide Entry / Show Entry for IDs and documents.
 *
 * hidden_at: when the record was hidden from residents (null = visible, the default).
 * hidden_by: who hid it (CMS only).
 *
 * Every existing record stays visible: nothing is hidden by this migration. Hiding never
 * deletes or changes record information, and it is separate from Last Reviewed, Agency
 * Confirmation and the research (encoding) status.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['government_ids', 'documents'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->timestamp('hidden_at')->nullable()->index();
                $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    // Removes only the visibility columns: every record becomes visible again, nothing else changes.
    public function down(): void
    {
        foreach (['government_ids', 'documents'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->dropConstrainedForeignId('hidden_by');
                $table->dropIndex($name.'_hidden_at_index');
                $table->dropColumn('hidden_at');
            });
        }
    }
};
