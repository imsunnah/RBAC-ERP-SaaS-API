<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->nullable()->unique();
                $table->foreignId('organization_id')->nullable();
                $table->foreignId('user_id')->nullable();
                $table->string('action');             // created, updated, deleted
                $table->string('auditable_type');      // Model class
                $table->foreignId('auditable_id');          // Model UUID
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('url')->nullable();
                $table->string('tags')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['auditable_type', 'auditable_id']);
                $table->index('organization_id');
                $table->index('user_id');
            });
        }

        if (!Schema::hasTable('user_login_attempts')) {
            Schema::create('user_login_attempts', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->nullable()->unique();
                $table->foreignId('user_id')->nullable();
                $table->string('email');
                $table->enum('status', ['success', 'failed', 'blocked'])->default('failed');
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('location')->nullable();
                $table->text('failure_reason')->nullable();
                $table->timestamp('attempted_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('password_histories')) {
            Schema::create('password_histories', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->nullable()->unique();
                $table->foreignId('user_id');
                $table->string('password');  // hashed
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('password_histories');
        Schema::dropIfExists('user_login_attempts');
        Schema::dropIfExists('audit_logs');
    }
};
