<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['government_id' => 'government_ids', 'document' => 'documents'] as $prefix => $owners) {
            Schema::create('catalog_'.$prefix.'_prerequisites', function (Blueprint $table) use ($owners) {
                $table->id();
                $table->foreignId('owner_id')->constrained($owners)->cascadeOnDelete();
                $table->string('type', 20);
                $table->foreignId('government_id_id')->nullable()->constrained()->restrictOnDelete();
                $table->foreignId('document_id')->nullable()->constrained()->restrictOnDelete();
                $table->string('custom_name')->nullable();
                $table->text('condition')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['owner_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_document_prerequisites');
        Schema::dropIfExists('catalog_government_id_prerequisites');
    }
};
