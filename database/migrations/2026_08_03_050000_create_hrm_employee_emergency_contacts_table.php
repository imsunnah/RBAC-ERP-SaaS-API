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
        if (!Schema::hasTable('hrm_employee_emergency_contacts')) {
            Schema::create('hrm_employee_emergency_contacts', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('hrm_employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->string('contact_name', 100)->nullable();
                $table->foreignId('relationship_id')->nullable()->constrained('global_lookups')->nullOnDelete();
                $table->string('contact_number', 20)->nullable();
                $table->string('alternate_contact_number', 20)->nullable();
                $table->string('address', 300)->nullable();
                $table->timestamps();
            });
        }

        Schema::table('hrm_employees', function (Blueprint $table) {
            if (Schema::hasColumn('hrm_employees', 'emergency_contact_relationship_id')) {
                $table->dropForeign(['emergency_contact_relationship_id']);
                $table->dropColumn('emergency_contact_relationship_id');
            }
            if (Schema::hasColumn('hrm_employees', 'emergency_contact_name')) {
                $table->dropColumn('emergency_contact_name');
            }
            if (Schema::hasColumn('hrm_employees', 'emergency_contact_number')) {
                $table->dropColumn('emergency_contact_number');
            }
            if (Schema::hasColumn('hrm_employees', 'emergency_contact_alternate_number')) {
                $table->dropColumn('emergency_contact_alternate_number');
            }
            if (Schema::hasColumn('hrm_employees', 'emergency_contact')) {
                $table->dropColumn('emergency_contact');
            }
            if (Schema::hasColumn('hrm_employees', 'emergency_address')) {
                $table->dropColumn('emergency_address');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hrm_employee_emergency_contacts');
    }
};
