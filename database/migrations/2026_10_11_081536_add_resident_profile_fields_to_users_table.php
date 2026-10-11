<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable()->change();

            $table->date('date_of_birth')
                ->nullable()
                ->after('name');

            $table->foreignId('barangay_id')
                ->nullable()
                ->after('date_of_birth')
                ->constrained('barangays');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('barangay_id');
            $table->dropColumn('date_of_birth');

            $table->string('name')->change();
        });
    }
};