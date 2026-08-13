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
        if (Schema::hasTable('hrm_leave_applications')) {
            Schema::table('hrm_leave_applications', function (Blueprint $table) {
                try {
                    $table->dropForeign(['leave_type_id']);
                } catch (\Throwable $e) {
                    // Foreign key might not exist yet or has a custom name
                }

                $table->foreign('leave_type_id')
                    ->references('id')
                    ->on('hrm_lookups')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('hrm_leave_applications')) {
            Schema::table('hrm_leave_applications', function (Blueprint $table) {
                try {
                    $table->dropForeign(['leave_type_id']);
                } catch (\Throwable $e) {
                    // Foreign key might not exist yet or has a custom name
                }

                $table->foreign('leave_type_id')
                    ->references('id')
                    ->on('global_lookups')
                    ->nullOnDelete();
            });
        }
    }
};
