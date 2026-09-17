<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->string('type')->after('description');

            $table->foreignId('referenced_government_id_id')
                ->nullable()
                ->after('type')
                ->constrained('government_ids')
                ->nullOnDelete();

            $table->foreignId('referenced_document_id')
                ->nullable()
                ->after('referenced_government_id_id')
                ->constrained('documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->dropForeign(['referenced_government_id_id']);
            $table->dropForeign(['referenced_document_id']);

            $table->dropColumn([
                'type',
                'referenced_government_id_id',
                'referenced_document_id',
            ]);
        });
    }
};