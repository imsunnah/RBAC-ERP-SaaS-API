<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id('shift_id');
            $table->string('name', 100);
            $table->string('short_code', 10)->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_overnight')->default(false);
            $table->integer('break_duration_minutes')->default(60);
            $table->decimal('expected_hours', 4, 2)->default(8.00);
            $table->integer('grace_minutes')->default(15);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('org_no')->nullable();
            $table->timestamps();

            $table->index(['org_no', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_shifts');
    }
};
