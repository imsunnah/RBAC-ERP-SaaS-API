<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hrm_attendances', function (Blueprint $table) {
            // Summary fields
            if (!Schema::hasColumn('hrm_attendances', 'status')) {
                $table->string('status')->nullable()->after('date');
            }
            if (!Schema::hasColumn('hrm_attendances', 'working_hours')) {
                $table->decimal('working_hours', 5, 2)->nullable()->after('punch_out');
            }
            if (!Schema::hasColumn('hrm_attendances', 'overtime_hours')) {
                $table->decimal('overtime_hours', 5, 2)->nullable()->after('working_hours');
            }
            if (!Schema::hasColumn('hrm_attendances', 'shift_id')) {
                $table->foreignId('shift_id')->nullable()->after('overtime_hours')->constrained('work_shifts', 'shift_id')->nullOnDelete();
            }
            if (!Schema::hasColumn('hrm_attendances', 'source')) {
                $table->string('source')->default('BIOMETRIC')->after('shift_id');
            }
            if (!Schema::hasColumn('hrm_attendances', 'approval_status')) {
                $table->string('approval_status')->default('APPROVED')->after('source');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hrm_attendances', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
            $table->dropColumn([
                'status',
                'working_hours',
                'overtime_hours',
                'shift_id',
                'source',
                'approval_status'
            ]);
        });
    }
};
