<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add new columns and make punch_time nullable
        Schema::table('hrm_attendances', function (Blueprint $table) {
            $table->date('date')->after('employee_id')->nullable();
            $table->dateTime('punch_in')->after('date')->nullable();
            $table->dateTime('punch_out')->after('punch_in')->nullable();
            $table->dateTime('punch_time')->nullable()->change();
        });

        // 2. Create a temporary table with aggregated data (only for PostgreSQL/Postgres)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                CREATE TEMPORARY TABLE hrm_attendances_temp AS
                SELECT 
                    CAST(MIN(uuid::text) AS uuid) as uuid,
                    company_id,
                    organization_id,
                    device_user_id,
                    biometric_id,
                    employee_id,
                    CAST(punch_time AS DATE) as date,
                    MIN(punch_time) as punch_in,
                    MAX(punch_time) as punch_out,
                    MIN(punch_state) as punch_state,
                    MIN(punch_type) as punch_type,
                    device_ip,
                    MAX(synced_at) as synced_at,
                    MIN(created_by) as created_by,
                    MIN(updated_by) as updated_by,
                    MIN(created_at) as created_at,
                    MAX(updated_at) as updated_at
                FROM hrm_attendances
                GROUP BY company_id, organization_id, device_user_id, biometric_id, employee_id, CAST(punch_time AS DATE), device_ip
            ");

            // 3. Truncate original table
            DB::table('hrm_attendances')->truncate();

            // 4. Re-insert from temp table
            DB::statement("
                INSERT INTO hrm_attendances (
                    uuid, company_id, organization_id, device_user_id, biometric_id, employee_id, 
                    date, punch_in, punch_out, punch_state, punch_type, device_ip, 
                    synced_at, created_by, updated_by, created_at, updated_at
                )
                SELECT 
                    uuid, company_id, organization_id, device_user_id, biometric_id, employee_id, 
                    date, punch_in, punch_out, punch_state, punch_type, device_ip, 
                    synced_at, created_by, updated_by, created_at, updated_at
                FROM hrm_attendances_temp
            ");
        }

        // 5. Drop old column and add unique index
        Schema::table('hrm_attendances', function (Blueprint $table) {
            // Drop unique index if it exists. 
            // In Laravel, we can't easily check for exists, so we use raw for PG 
            // and we can try to drop normally for others.
            if (DB::getDriverName() !== 'sqlite') {
                DB::statement('ALTER TABLE hrm_attendances DROP CONSTRAINT IF EXISTS zk_att_unique');
            } else {
                // For SQLite, we can just drop it via Schema
                $table->dropUnique('zk_att_unique');
            }

            $table->dropColumn('punch_time');
            $table->unique(['device_user_id', 'date', 'device_ip'], 'zk_att_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hrm_attendances', function (Blueprint $table) {
            $table->dropUnique('zk_att_unique');
            $table->dateTime('punch_time')->after('employee_id')->nullable();
            $table->dropColumn(['date', 'punch_in', 'punch_out']);
            $table->unique(['device_user_id', 'punch_time', 'device_ip'], 'zk_att_unique');
        });
    }
};
