<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Tables that need a uuid column added.
     * Format: 'table_name' => 'primary_key_column'
     */
    private array $tables = [
        'adms_devices'    => 'id',
        'work_shifts'     => 'shift_id',
        'duty_rosters'    => 'id',
        'payroll_configs' => 'id',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $pk) {
            if (Schema::hasColumn($table, 'uuid')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->uuid('uuid')->nullable()->unique()->after('id');
            });

            // Backfill existing rows with generated UUIDs
            DB::table($table)->whereNull('uuid')->orderBy($pk)->each(function ($row) use ($table, $pk) {
                DB::table($table)
                    ->where($pk, $row->{$pk})
                    ->update(['uuid' => Str::uuid()->toString()]);
            });

            // Now make it non-nullable
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->uuid('uuid')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table => $pk) {
            if (Schema::hasColumn($table, 'uuid')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('uuid');
                });
            }
        }
    }
};
