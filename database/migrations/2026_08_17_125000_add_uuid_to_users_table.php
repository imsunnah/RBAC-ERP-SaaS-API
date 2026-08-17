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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'uuid')) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
            }
        });

        // Generate UUIDs for any existing users
        try {
            $users = \App\Models\User::whereNull('uuid')->orWhere('uuid', '')->get();
            foreach ($users as $user) {
                $user->uuid = (string) \Illuminate\Support\Str::uuid();
                $user->save();
            }
        } catch (\Throwable $e) {
            // Silence if model class or table isn't fully ready
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'uuid')) {
                $table->dropColumn('uuid');
            }
        });
    }
};
