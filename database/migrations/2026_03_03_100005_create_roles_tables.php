<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('organization_id');
            $table->foreignId('parent_role_id')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->boolean('is_inheritable')->default(true);
            $table->integer('level')->default(0); // hierarchy depth
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('parent_role_id')->references('id')->on('roles')->onDelete('set null');
            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('role_hierarchy', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('ancestor_id');
            $table->foreignId('descendant_id');
            $table->integer('depth')->default(0);
            $table->timestamps();

            $table->foreign('ancestor_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('descendant_id')->references('id')->on('roles')->onDelete('cascade');
            $table->unique(['ancestor_id', 'descendant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_hierarchy');
        Schema::dropIfExists('roles');
    }
};
