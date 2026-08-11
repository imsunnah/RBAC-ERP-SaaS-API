<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hrm_employee_meal_selections')) {
            Schema::create('hrm_employee_meal_selections', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
                $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();

                $table->foreignId('hrm_employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->foreignId('hrm_weekly_meal_id')->constrained('hrm_weekly_meals')->cascadeOnDelete();
                $table->date('date');
                
                $table->string('selection', 20)->default('standard'); // standard, alternative, none
                $table->boolean('is_manually_updated')->default(false);

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['hrm_employee_id', 'date'], 'emp_meal_selection_date_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_employee_meal_selections');
    }
};
