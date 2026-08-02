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
        Schema::table('hrm_employees', function (Blueprint $table) {
            $table->string('passport_no')->nullable()->after('national_id');
            $table->date('passport_issue')->nullable()->after('passport_no');
            $table->date('passport_expiry')->nullable()->after('passport_issue');
            $table->string('nationality')->nullable()->after('passport_expiry');
            $table->foreignId('reporting_manager_id')->nullable()->after('hired_by')->constrained('hrm_employees')->nullOnDelete();
            $table->string('work_location')->nullable()->after('work_shift_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hrm_employees', function (Blueprint $table) {
            $table->dropForeign(['reporting_manager_id']);
            $table->dropColumn([
                'passport_no',
                'passport_issue',
                'passport_expiry',
                'nationality',
                'reporting_manager_id',
                'work_location'
            ]);
        });
    }
};
