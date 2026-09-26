<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hrm_employee_emergency_contacts', function (Blueprint $table) {
            $table->dropForeign(['relationship_id']);
            $table->foreign('relationship_id')
                ->references('id')
                ->on('hrm_lookups')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hrm_employee_emergency_contacts', function (Blueprint $table) {
            $table->dropForeign(['relationship_id']);
            $table->foreign('relationship_id')
                ->references('id')
                ->on('global_lookups')
                ->nullOnDelete();
        });
    }
};
