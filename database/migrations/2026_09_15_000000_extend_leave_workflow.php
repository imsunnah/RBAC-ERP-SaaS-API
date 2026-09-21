<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hrm_leave_applications', function (Blueprint $table) {
            $table->string('start_session', 20)->default('full_day')->after('to_date');
            $table->string('end_session', 20)->default('full_day')->after('start_session');
            $table->decimal('total_days', 6, 2)->default(0)->change();
            $table->timestamp('cancelled_at')->nullable()->after('approved_at');
        });

        Schema::table('hrm_leave_balances', function (Blueprint $table) {
            $table->decimal('pending_days', 6, 2)->default(0)->after('used_days');
        });
    }

    public function down(): void
    {
        Schema::table('hrm_leave_applications', function (Blueprint $table) {
            $table->dropColumn(['start_session', 'end_session', 'cancelled_at']);
            $table->integer('total_days')->default(0)->change();
        });
        Schema::table('hrm_leave_balances', fn (Blueprint $table) => $table->dropColumn('pending_days'));
    }
};