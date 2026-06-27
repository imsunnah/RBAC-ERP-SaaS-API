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
                $table->renameColumn('gender_uuid', 'gender_id');
            }
            if (Schema::hasColumn('hrm_employees', 'religion_uuid')) {
                $table->renameColumn('religion_uuid', 'religion_id');
            }
            if (Schema::hasColumn('hrm_employees', 'marital_status_uuid')) {
                $table->renameColumn('marital_status_uuid', 'marital_status_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hrm_employees', function (Blueprint $table) {
            $table->renameColumn('gender_id', 'gender_uuid');
            $table->renameColumn('religion_id', 'religion_uuid');
            $table->renameColumn('marital_status_id', 'marital_status_uuid');
        });
    }
};
