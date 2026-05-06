<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('user_organizations')) {
            Schema::create('user_organizations', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->nullable()->unique();
                $table->foreignId('user_id');
                $table->foreignId('organization_id');
                $table->boolean('is_owner')->default(false);
                $table->boolean('is_primary')->default(false);
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamp('joined_at')->useCurrent();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
                $table->unique(['user_id', 'organization_id']);
            });
        } else {
            Schema::table('user_organizations', function (Blueprint $table) {
                if (!Schema::hasColumn('user_organizations', 'uuid')) $table->uuid('uuid')->nullable()->unique()->after('id');
                if (!Schema::hasColumn('user_organizations', 'user_id')) $table->foreignId('user_id')->after('uuid');
                if (!Schema::hasColumn('user_organizations', 'organization_id')) $table->foreignId('organization_id')->after('user_id');
                if (!Schema::hasColumn('user_organizations', 'is_owner')) $table->boolean('is_owner')->default(false)->after('organization_id');
                if (!Schema::hasColumn('user_organizations', 'is_primary')) $table->boolean('is_primary')->default(false)->after('is_owner');
                if (!Schema::hasColumn('user_organizations', 'status')) $table->enum('status', ['active', 'inactive'])->default('active')->after('is_primary');
                if (!Schema::hasColumn('user_organizations', 'joined_at')) $table->timestamp('joined_at')->useCurrent()->after('status');
                if (!Schema::hasColumn('user_organizations', 'deleted_at')) $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_organizations');
    }
};
