<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duty_rosters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
            $table->unsignedBigInteger('shift_id');
            $table->date('date');
            $table->boolean('is_off_day')->default(false);
            $table->string('remarks', 255)->nullable();
            $table->integer('org_no')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
            $table->index(['date', 'shift_id']);

            $table->foreign('shift_id')->references('shift_id')->on('work_shifts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duty_rosters');
    }
};
