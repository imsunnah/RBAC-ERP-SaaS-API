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
        if (!Schema::hasTable('hrm_employee_documents')) {
            Schema::create('hrm_employee_documents', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('hrm_employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->string('title', 255);
                $table->string('doc', 500)->nullable(); // stored file path
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hrm_employee_documents');
    }
};
