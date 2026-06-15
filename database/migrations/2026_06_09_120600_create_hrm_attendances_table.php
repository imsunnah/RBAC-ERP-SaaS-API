<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hrm_attendances', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();

            $table->string('device_user_id');
            $table->string('biometric_id')->nullable();
            $table->foreignId('employee_id')->nullable()->constrained('hrm_employees')->nullOnDelete();

            $table->dateTime('punch_time');
            $table->tinyInteger('punch_state')->nullable();
            $table->tinyInteger('punch_type')->nullable();
            $table->string('device_ip')->nullable();

            $table->dateTime('synced_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['device_user_id', 'punch_time', 'device_ip'], 'zk_att_unique');
            $table->index(['company_id', 'organization_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_attendances');
    }
};
