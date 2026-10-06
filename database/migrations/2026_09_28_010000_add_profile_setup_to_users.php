<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('owned_ids')->nullable();
            $table->json('owned_documents')->nullable();
            $table->timestamp('profile_setup_completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['owned_ids', 'owned_documents', 'profile_setup_completed_at']);
        });
    }
};
