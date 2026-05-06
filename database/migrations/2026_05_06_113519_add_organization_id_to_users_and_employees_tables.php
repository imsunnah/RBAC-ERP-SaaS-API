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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'organization_id')) {
                $table->foreignId('organization_id')->nullable()->after('company_id')->constrained('organizations')->nullOnDelete();
            }
        });

        Schema::table('hrm_employees', function (Blueprint $table) {
            if (!Schema::hasColumn('hrm_employees', 'organization_id')) {
                $table->foreignId('organization_id')->nullable()->after('company_id')->constrained('organizations')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'organization_id')) {
                $table->dropForeign(['organization_id']);
                $table->dropColumn('organization_id');
            }
        });

        Schema::table('hrm_employees', function (Blueprint $table) {
            if (Schema::hasColumn('hrm_employees', 'organization_id')) {
                $table->dropForeign(['organization_id']);
                $table->dropColumn('organization_id');
            }
        });
    }
};
