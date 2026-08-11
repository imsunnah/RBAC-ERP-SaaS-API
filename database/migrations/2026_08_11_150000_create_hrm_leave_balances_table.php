<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hrm_leave_balances')) {
            Schema::create('hrm_leave_balances', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
                $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
                
                $table->foreignId('hrm_employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->foreignId('leave_type_id')->constrained('hrm_lookups')->cascadeOnDelete();
                $table->integer('year');
                $table->decimal('total_days', 5, 2)->default(0.00);
                $table->decimal('used_days', 5, 2)->default(0.00);
                
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['hrm_employee_id', 'leave_type_id', 'year'], 'emp_leave_year_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_leave_balances');
    }
};
