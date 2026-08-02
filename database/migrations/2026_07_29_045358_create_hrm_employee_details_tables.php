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
        Schema::create('hrm_employee_education', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hrm_employee_id')->constrained('hrm_employees')->cascadeOnDelete();
            $table->string('degree_name');
            $table->string('institution');
            $table->integer('passing_year')->nullable();
            $table->string('duration')->nullable();
            $table->timestamps();
        });

        Schema::create('hrm_employee_experience', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hrm_employee_id')->constrained('hrm_employees')->cascadeOnDelete();
            $table->string('company_name');
            $table->string('designation');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('hrm_employee_family', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hrm_employee_id')->constrained('hrm_employees')->cascadeOnDelete();
            $table->string('full_name');
            $table->date('date_of_birth')->nullable();
            $table->foreignId('gender_id')->nullable()->constrained('global_lookups')->nullOnDelete();
            $table->foreignId('marital_status_id')->nullable()->constrained('global_lookups')->nullOnDelete();
            $table->string('national_id', 20)->nullable();
            $table->foreignId('blood_group_id')->nullable()->constrained('global_lookups')->nullOnDelete();
            $table->string('nationality')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hrm_employee_family');
        Schema::dropIfExists('hrm_employee_experience');
        Schema::dropIfExists('hrm_employee_education');
    }
};
