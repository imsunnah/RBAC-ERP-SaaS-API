<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. designations (Tenant-specific)
        if (!Schema::hasTable('designations')) {
            Schema::create('designations', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('name');
                $table->string('slug');
                $table->foreignId('organization_id')->constrained('organizations')->onDelete('cascade');
                $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. genders (Global)
        if (!Schema::hasTable('genders')) {
            Schema::create('genders', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('name', 50);
                $table->string('slug', 50)->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 3. religions (Global)
        if (!Schema::hasTable('religions')) {
            Schema::create('religions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('name', 100);
                $table->string('slug', 100)->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 4. marital_statuses (Global)
        if (!Schema::hasTable('marital_statuses')) {
            Schema::create('marital_statuses', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('name', 100);
                $table->string('slug', 100)->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marital_statuses');
        Schema::dropIfExists('religions');
        Schema::dropIfExists('genders');
        Schema::dropIfExists('designations');
    }
};
