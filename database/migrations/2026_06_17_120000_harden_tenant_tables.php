<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. adms_devices
        Schema::table('adms_devices', function (Blueprint $table) {
            if (!Schema::hasColumn('adms_devices', 'organization_id')) {
                $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->onDelete('cascade');
            }
            if (!Schema::hasColumn('adms_devices', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('organization_id')->constrained('companies')->onDelete('cascade');
            }
            if (!Schema::hasColumn('adms_devices', 'uuid')) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
            }
        });

        // 2. work_shifts
        Schema::table('work_shifts', function (Blueprint $table) {
            if (!Schema::hasColumn('work_shifts', 'organization_id')) {
                $table->foreignId('organization_id')->nullable()->after('shift_id')->constrained('organizations')->onDelete('cascade');
            }
            if (!Schema::hasColumn('work_shifts', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('organization_id')->constrained('companies')->onDelete('cascade');
            }
            if (!Schema::hasColumn('work_shifts', 'uuid')) {
                $table->uuid('uuid')->nullable()->unique()->after('shift_id');
            }
        });

        // 3. duty_rosters
        Schema::table('duty_rosters', function (Blueprint $table) {
            if (!Schema::hasColumn('duty_rosters', 'organization_id')) {
                $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->onDelete('cascade');
            }
            if (!Schema::hasColumn('duty_rosters', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('organization_id')->constrained('companies')->onDelete('cascade');
            }
            if (!Schema::hasColumn('duty_rosters', 'uuid')) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
            }
        });

        // 4. payroll_configs
        Schema::table('payroll_configs', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_configs', 'organization_id')) {
                $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->onDelete('cascade');
            }
            if (!Schema::hasColumn('payroll_configs', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('organization_id')->constrained('companies')->onDelete('cascade');
            }
            if (!Schema::hasColumn('payroll_configs', 'uuid')) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
            }
        });

        // 5. departments
        Schema::table('departments', function (Blueprint $table) {
            if (!Schema::hasColumn('departments', 'name')) {
                $table->string('name')->after('id')->nullable();
            }
            if (!Schema::hasColumn('departments', 'slug')) {
                $table->string('slug')->after('name')->nullable();
            }
            if (!Schema::hasColumn('departments', 'organization_id')) {
                $table->foreignId('organization_id')->nullable()->after('slug')->constrained('organizations')->onDelete('cascade');
            }
            if (!Schema::hasColumn('departments', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('organization_id')->constrained('companies')->onDelete('cascade');
            }
            if (!Schema::hasColumn('departments', 'uuid')) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
            }
        });
    }

    public function down(): void
    {
        // Not implemented for simplicity, usually not needed in this dev context
    }
};
