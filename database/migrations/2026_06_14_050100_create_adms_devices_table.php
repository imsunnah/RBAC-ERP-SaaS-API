<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adms_devices', function (Blueprint $table) {
            $table->id();
            $table->string('serial_number', 50)->unique();
            $table->string('alias', 100)->nullable();
            $table->string('name', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('firmware', 100)->nullable();
            $table->string('platform', 50)->nullable();
            $table->dateTime('last_seen')->nullable();
            $table->dateTime('last_record_at')->nullable();
            $table->integer('push_count')->default(0);
            $table->integer('total_records_sent')->default(0);
            $table->integer('user_count')->nullable();
            $table->integer('fp_count')->nullable();
            $table->integer('tx_count')->nullable();
            $table->integer('org_no')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adms_devices');
    }
};
