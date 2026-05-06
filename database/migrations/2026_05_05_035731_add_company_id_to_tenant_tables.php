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
        // 1. users
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->onDelete('cascade');
                $table->index(['company_id', 'status']);
            }
        });

        // 2. roles
        Schema::table('roles', function (Blueprint $table) {
            if (!Schema::hasColumn('roles', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('organization_id')->constrained('companies')->onDelete('cascade');
                $table->index(['company_id', 'is_active']);
            }
        });

        // 3. user_roles
        Schema::table('user_roles', function (Blueprint $table) {
            if (!Schema::hasColumn('user_roles', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('organization_id')->constrained('companies')->onDelete('cascade');
            }
        });

        // 4. user_permissions
        Schema::table('user_permissions', function (Blueprint $table) {
            if (!Schema::hasColumn('user_permissions', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('organization_id')->constrained('companies')->onDelete('cascade');
            }
        });

        // 5. audit_logs
        Schema::table('audit_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('audit_logs', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('organization_id')->constrained('companies')->onDelete('cascade');
                $table->index(['company_id', 'created_at']);
            }
        });

        // 6. user_organizations
        Schema::table('user_organizations', function (Blueprint $table) {
            if (!Schema::hasColumn('user_organizations', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('organization_id')->constrained('companies')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_organizations', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropIndex(['company_id', 'created_at']);
            $table->dropColumn('company_id');
        });

        Schema::table('user_permissions', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });

        Schema::table('user_roles', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropIndex(['company_id', 'is_active']);
            $table->dropColumn('company_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropIndex(['company_id', 'status']);
            $table->dropColumn('company_id');
        });
    }
};
