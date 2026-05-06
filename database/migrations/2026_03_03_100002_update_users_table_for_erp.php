<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'uuid')) $table->uuid('uuid')->nullable()->unique()->after('id');
            if (!Schema::hasColumn('users', 'first_name')) $table->string('first_name')->nullable()->after('id');
            if (!Schema::hasColumn('users', 'last_name')) $table->string('last_name')->nullable()->after('first_name');
            if (!Schema::hasColumn('users', 'avatar')) $table->string('avatar')->nullable()->after('email');
            if (!Schema::hasColumn('users', 'phone')) $table->string('phone', 30)->nullable()->after('avatar');
            if (!Schema::hasColumn('users', 'is_super_admin')) $table->boolean('is_super_admin')->default(false)->after('phone');
            if (!Schema::hasColumn('users', 'status')) $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->after('is_super_admin');
            if (!Schema::hasColumn('users', 'last_login_at')) $table->timestamp('last_login_at')->nullable()->after('status');
            if (!Schema::hasColumn('users', 'last_login_ip')) $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            if (!Schema::hasColumn('users', 'deleted_at')) $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'last_name',
                'avatar',
                'phone',
                'is_super_admin',
                'status',
                'last_login_at',
                'last_login_ip',
                'deleted_at',
            ]);
        });
    }
};
