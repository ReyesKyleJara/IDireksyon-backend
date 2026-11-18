<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which residents an office serves, so barangay-issued IDs and documents can show each
     * resident their own barangay's office.
     *
     * coverage:
     *   null       - not yet classified (the default for every existing office)
     *   'barangay' - serves one barangay only; barangay_id says which
     *   'shared'   - serves residents of every barangay (e.g. a municipal office)
     */
    public function up(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->string('coverage', 20)->nullable()->after('province');
            $table->foreignId('barangay_id')
                ->nullable()
                ->after('coverage')
                ->constrained('barangays')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('barangay_id');
            $table->dropColumn('coverage');
        });
    }
};
