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
        // 7. user_login_attempts
        Schema::table('user_login_attempts', function (Blueprint $table) {
            if (!Schema::hasColumn('user_login_attempts', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('user_id')->constrained('companies')->onDelete('cascade');
            }
        });

        // 8. password_histories
        Schema::table('password_histories', function (Blueprint $table) {
            if (!Schema::hasColumn('password_histories', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('user_id')->constrained('companies')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('password_histories', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });

        Schema::table('user_login_attempts', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
