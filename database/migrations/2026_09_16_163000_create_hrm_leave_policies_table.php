<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hrm_leave_policies')) {
            Schema::create('hrm_leave_policies', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
                $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
                $table->foreignId('leave_type_id')->constrained('hrm_lookups')->cascadeOnDelete();
                $table->foreignId('employee_type_id')->nullable()->constrained('hrm_lookups')->nullOnDelete();
                $table->decimal('leave_balance', 5, 2)->default(0.00);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(
                    ['organization_id', 'leave_type_id', 'employee_type_id'],
                    'hrm_leave_policies_org_type_employee_unique'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_leave_policies');
    }
};
