<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('user_modules')) {
            Schema::create('user_modules', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->nullable()->unique();
                $table->foreignId('user_id');
                $table->foreignId('module_id');
                $table->foreignId('organization_id');
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('module_id')->references('id')->on('modules')->onDelete('cascade');
                $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
                $table->unique(['user_id', 'module_id', 'organization_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_modules');
    }
};
