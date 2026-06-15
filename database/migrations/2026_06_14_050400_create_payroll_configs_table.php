<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_configs', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100);
            $table->string('value', 255);
            $table->string('type', 20)->default('string');
            $table->integer('org_no')->nullable();
            $table->timestamps();

            $table->unique(['key', 'org_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_configs');
    }
};
