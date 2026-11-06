<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'resident_id_preparations' => 'government_id_requirement_items',
            'resident_document_preparations' => 'document_requirement_items',
        ] as $name => $items) {
            Schema::create($name, function (Blueprint $table) use ($name, $items) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('requirement_item_id')->constrained($items)->cascadeOnDelete();
                $table->boolean('prepared')->default(false);
                $table->timestamps();
                $table->unique(['user_id', 'requirement_item_id'], $name.'_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_document_preparations');
        Schema::dropIfExists('resident_id_preparations');
    }
};
