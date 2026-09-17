<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
        foreach (['barangays', 'agencies', 'categories'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->text('description')->nullable();
                $table->string('status')->default('active');
            });
        }
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('parent_category_id')->nullable()->constrained('categories')->restrictOnDelete();
        });
        Schema::table('offices', function (Blueprint $table) {
            $table->string('municipality')->nullable();
            $table->string('province')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->text('source_url')->nullable();
            $table->date('source_checked_at')->nullable();
            $table->string('status')->default('needs_research');
        });
        Schema::create('office_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('opens_at');
            $table->time('closes_at');
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->unique(['office_id', 'day_of_week', 'opens_at']);
        });
        foreach (['government_ids', 'documents'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('level_id')->nullable()->constrained()->restrictOnDelete();
                $table->string('record_type')->default('other');
                $table->string('availability_status')->default('unknown');
            });
        }
        foreach (['barangay' => 'Barangay', 'municipal' => 'Municipal', 'national' => 'National'] as $slug => $name) {
            $id = DB::table('levels')->insertGetId(['name' => $name, 'slug' => $slug, 'created_at' => now(), 'updated_at' => now()]);
            foreach (['government_ids', 'documents'] as $table) {
                DB::table($table)->where('issuance_level', $slug)->update(['level_id' => $id]);
            }
        }
    }

    public function down(): void
    {
        foreach (['government_ids', 'documents'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('level_id');
                $table->dropColumn(['record_type', 'availability_status']);
            });
        }
        Schema::dropIfExists('office_hours');
        Schema::table('offices', fn (Blueprint $table) => $table->dropColumn(['municipality', 'province', 'phone', 'email', 'notes', 'source_url', 'source_checked_at', 'status']));
        Schema::table('categories', fn (Blueprint $table) => $table->dropConstrainedForeignId('parent_category_id'));
        foreach (['barangays', 'agencies', 'categories'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['description', 'status']));
        }
        Schema::dropIfExists('levels');
    }
};
