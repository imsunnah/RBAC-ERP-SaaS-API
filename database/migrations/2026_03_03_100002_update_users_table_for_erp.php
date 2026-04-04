<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Convert id to UUID
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->string('first_name')->nullable()->after('id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('avatar')->nullable()->after('email');
            $table->string('phone', 30)->nullable()->after('avatar');
            $table->boolean('is_super_admin')->default(false)->after('phone');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->after('is_super_admin');
            $table->timestamp('last_login_at')->nullable()->after('status');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->softDeletes();
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
