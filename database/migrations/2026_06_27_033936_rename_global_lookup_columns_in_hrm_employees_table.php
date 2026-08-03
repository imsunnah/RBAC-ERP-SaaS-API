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
            if (Schema::hasColumn('hrm_employees', 'gender_uuid')) {
                if (Schema::hasColumn('hrm_employees', 'gender_id')) {
                    $table->dropColumn('gender_uuid');
                } else {
                    $table->renameColumn('gender_uuid', 'gender_id');
                }
            }

            if (Schema::hasColumn('hrm_employees', 'religion_uuid')) {
                if (Schema::hasColumn('hrm_employees', 'religion_id')) {
                    $table->dropColumn('religion_uuid');
                } else {
                    $table->renameColumn('religion_uuid', 'religion_id');
                }
            }

            if (Schema::hasColumn('hrm_employees', 'marital_status_uuid')) {
                if (Schema::hasColumn('hrm_employees', 'marital_status_id')) {
                    $table->dropColumn('marital_status_uuid');
                } else {
                    $table->renameColumn('marital_status_uuid', 'marital_status_id');
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
