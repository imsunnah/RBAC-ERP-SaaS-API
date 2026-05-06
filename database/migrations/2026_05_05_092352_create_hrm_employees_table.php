<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('hrm_employees')) {
            Schema::create('hrm_employees', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->nullable()->unique();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                
                // Core Identification
                $table->string('user_employee_id', 16)->nullable()->unique();
                $table->string('biometric_id', 16)->nullable();
                $table->string('national_id', 20)->nullable();
                
                // Personal Details
                $table->string('full_name', 100);
                $table->string('employee_name_local', 300)->nullable();
                $table->date('date_of_birth')->nullable();
                $table->string('gender_id', 3)->nullable();
                $table->string('blood_group_id', 3)->nullable();
                $table->bigInteger('religion_id')->nullable();
                $table->bigInteger('marital_status_id')->nullable();
                $table->bigInteger('country_id')->nullable();
                $table->decimal('weight_kg', 5, 2)->nullable();
                $table->decimal('height_cm', 5, 2)->nullable();
                $table->string('eye_color', 100)->nullable();
                
                // Contact & Address
                $table->string('office_email', 100)->nullable();
                $table->string('personal_email', 100)->nullable();
                $table->string('alternate_email', 100)->nullable();
                $table->string('office_mobile_no', 15)->nullable();
                $table->string('personal_mobile_no', 15)->nullable();
                $table->string('secondary_mobile_no', 15)->nullable();
                $table->string('tertiary_mobile_no', 15)->nullable();
                $table->string('emergency_contact', 6)->nullable();
                $table->string('present_address', 300)->nullable();
                $table->string('permanent_address', 300)->nullable();
                $table->string('emergency_address', 300)->nullable();
                $table->string('address_link', 300)->nullable();
                
                // Employment Information
                $table->string('department_id', 14)->nullable();
                $table->bigInteger('designation_id')->nullable();
                $table->bigInteger('employee_type_id')->nullable();
                $table->smallInteger('employee_category_id')->nullable();
                $table->integer('employment_type_id')->nullable();
                $table->bigInteger('work_shift_id')->nullable();
                $table->date('hire_date')->nullable();
                $table->date('start_date')->nullable();
                $table->integer('employment_duration')->nullable();
                $table->bigInteger('hired_by')->nullable();
                
                // Termination Details
                $table->date('termination_date')->nullable();
                $table->string('terminated_by', 20)->nullable();
                $table->string('termination_reason', 200)->nullable();
                $table->string('termination_remarks', 200)->nullable();
                
                // Media/Images
                $table->string('employee_photo', 200)->nullable();
                $table->string('profile_picture', 200)->nullable();
                $table->string('signature_image', 200)->nullable();
                $table->string('nid_image', 200)->nullable();
                $table->string('business_card_image', 200)->nullable();
                
                // System
                $table->bigInteger('user_serial_no')->nullable();
                $table->smallInteger('is_active')->default(1);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                
                // Indexes for frequently queried fields
                $table->index(['company_id', 'is_active']);
                $table->index('user_employee_id');
            });
        } else {
            Schema::table('hrm_employees', function (Blueprint $table) {
                if (!Schema::hasColumn('hrm_employees', 'uuid')) $table->uuid('uuid')->nullable()->unique()->after('id');
                if (!Schema::hasColumn('hrm_employees', 'company_id')) $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                if (!Schema::hasColumn('hrm_employees', 'user_employee_id')) $table->string('user_employee_id', 16)->nullable()->unique();
                if (!Schema::hasColumn('hrm_employees', 'biometric_id')) $table->string('biometric_id', 16)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'national_id')) $table->string('national_id', 20)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'full_name')) $table->string('full_name', 100);
                if (!Schema::hasColumn('hrm_employees', 'employee_name_local')) $table->string('employee_name_local', 300)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'date_of_birth')) $table->date('date_of_birth')->nullable();
                if (!Schema::hasColumn('hrm_employees', 'gender_id')) $table->string('gender_id', 3)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'blood_group_id')) $table->string('blood_group_id', 3)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'religion_id')) $table->bigInteger('religion_id')->nullable();
                if (!Schema::hasColumn('hrm_employees', 'marital_status_id')) $table->bigInteger('marital_status_id')->nullable();
                if (!Schema::hasColumn('hrm_employees', 'country_id')) $table->bigInteger('country_id')->nullable();
                if (!Schema::hasColumn('hrm_employees', 'weight_kg')) $table->decimal('weight_kg', 5, 2)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'height_cm')) $table->decimal('height_cm', 5, 2)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'eye_color')) $table->string('eye_color', 100)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'office_email')) $table->string('office_email', 100)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'personal_email')) $table->string('personal_email', 100)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'alternate_email')) $table->string('alternate_email', 100)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'office_mobile_no')) $table->string('office_mobile_no', 15)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'personal_mobile_no')) $table->string('personal_mobile_no', 15)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'secondary_mobile_no')) $table->string('secondary_mobile_no', 15)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'tertiary_mobile_no')) $table->string('tertiary_mobile_no', 15)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'emergency_contact')) $table->string('emergency_contact', 6)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'present_address')) $table->string('present_address', 300)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'permanent_address')) $table->string('permanent_address', 300)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'emergency_address')) $table->string('emergency_address', 300)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'address_link')) $table->string('address_link', 300)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'department_id')) $table->string('department_id', 14)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'designation_id')) $table->bigInteger('designation_id')->nullable();
                if (!Schema::hasColumn('hrm_employees', 'employee_type_id')) $table->bigInteger('employee_type_id')->nullable();
                if (!Schema::hasColumn('hrm_employees', 'employee_category_id')) $table->smallInteger('employee_category_id')->nullable();
                if (!Schema::hasColumn('hrm_employees', 'employment_type_id')) $table->integer('employment_type_id')->nullable();
                if (!Schema::hasColumn('hrm_employees', 'work_shift_id')) $table->bigInteger('work_shift_id')->nullable();
                if (!Schema::hasColumn('hrm_employees', 'hire_date')) $table->date('hire_date')->nullable();
                if (!Schema::hasColumn('hrm_employees', 'start_date')) $table->date('start_date')->nullable();
                if (!Schema::hasColumn('hrm_employees', 'employment_duration')) $table->integer('employment_duration')->nullable();
                if (!Schema::hasColumn('hrm_employees', 'hired_by')) $table->bigInteger('hired_by')->nullable();
                if (!Schema::hasColumn('hrm_employees', 'termination_date')) $table->date('termination_date')->nullable();
                if (!Schema::hasColumn('hrm_employees', 'terminated_by')) $table->string('terminated_by', 20)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'termination_reason')) $table->string('termination_reason', 200)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'termination_remarks')) $table->string('termination_remarks', 200)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'employee_photo')) $table->string('employee_photo', 200)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'profile_picture')) $table->string('profile_picture', 200)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'signature_image')) $table->string('signature_image', 200)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'nid_image')) $table->string('nid_image', 200)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'business_card_image')) $table->string('business_card_image', 200)->nullable();
                if (!Schema::hasColumn('hrm_employees', 'user_serial_no')) $table->bigInteger('user_serial_no')->nullable();
                if (!Schema::hasColumn('hrm_employees', 'is_active')) $table->smallInteger('is_active')->default(1);
                if (!Schema::hasColumn('hrm_employees', 'created_by')) $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                if (!Schema::hasColumn('hrm_employees', 'updated_by')) $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                if (!Schema::hasColumn('hrm_employees', 'created_at')) $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_employees');
    }
};
