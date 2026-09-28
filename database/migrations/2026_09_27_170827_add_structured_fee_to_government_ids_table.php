<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('government_ids', function (Blueprint $table) {
            $table->string('fee_type', 30)
                ->nullable()
                ->after('fee');

            $table->decimal('fee_min', 10, 2)
                ->nullable()
                ->after('fee_type');

            $table->decimal('fee_max', 10, 2)
                ->nullable()
                ->after('fee_min');

            $table->string('fee_currency', 10)
                ->nullable()
                ->default('PHP')
                ->after('fee_max');

            $table->text('fee_notes')
                ->nullable()
                ->after('fee_currency');
        });
    }

    public function down(): void
    {
        Schema::table('government_ids', function (Blueprint $table) {
            $table->dropColumn([
                'fee_type',
                'fee_min',
                'fee_max',
                'fee_currency',
                'fee_notes',
            ]);
        });
    }
};