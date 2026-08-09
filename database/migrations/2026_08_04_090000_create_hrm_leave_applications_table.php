<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hrm_leave_applications')) {
            Schema::create('hrm_leave_applications', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
                $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
                $table->foreignId('hrm_employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->foreignId('leave_type_id')->nullable()->constrained('hrm_lookups')->nullOnDelete();
                $table->date('from_date');
                $table->date('to_date');
                $table->integer('total_days')->default(0);
                $table->text('reason')->nullable();
                $table->string('status', 20)->default('pending'); // pending, approved, rejected, cancelled
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hrm_leave_application_documents')) {
            Schema::create('hrm_leave_application_documents', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('hrm_leave_application_id')->constrained('hrm_leave_applications')->cascadeOnDelete();
                $table->string('title', 255)->nullable();
                $table->string('file_name', 255)->nullable();
                $table->string('file_path', 500);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_leave_application_documents');
        Schema::dropIfExists('hrm_leave_applications');
    }
};
