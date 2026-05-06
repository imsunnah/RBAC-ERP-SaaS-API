<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->nullable()->unique();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('domain')->nullable()->unique();
                $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            Schema::table('companies', function (Blueprint $table) {
                if (!Schema::hasColumn('companies', 'uuid')) {
                    $table->uuid('uuid')->nullable()->unique()->after('id');
                }
                if (!Schema::hasColumn('companies', 'name')) {
                    $table->string('name')->after('uuid');
                }
                if (!Schema::hasColumn('companies', 'slug')) {
                    $table->string('slug')->unique()->after('name');
                }
                if (!Schema::hasColumn('companies', 'domain')) {
                    $table->string('domain')->nullable()->unique()->after('slug');
                }
                if (!Schema::hasColumn('companies', 'status')) {
                    $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->after('domain');
                }
                if (!Schema::hasColumn('companies', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
