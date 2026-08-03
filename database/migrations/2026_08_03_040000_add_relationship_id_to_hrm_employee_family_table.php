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
        Schema::table('hrm_employee_family', function (Blueprint $table) {
            $table->foreignId('relationship_id')
                ->nullable()
                ->after('marital_status_id')
                ->constrained('global_lookups')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hrm_employee_family', function (Blueprint $table) {
            $table->dropForeign(['relationship_id']);
            $table->dropColumn('relationship_id');
        });
    }
};
