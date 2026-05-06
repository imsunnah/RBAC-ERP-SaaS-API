<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('organization_plans')) {
            Schema::create('organization_plans', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->nullable()->unique();
                $table->foreignId('organization_id');
                $table->string('plan_name');
                $table->integer('max_users')->default(5);
                $table->integer('max_modules')->default(3);
                $table->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly');
                $table->enum('status', ['trial', 'active', 'expired', 'cancelled'])->default('trial');
                $table->decimal('amount', 10, 2)->default(0.00);
                $table->string('currency', 10)->default('USD');
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->json('features')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            });
        } else {
            Schema::table('organization_plans', function (Blueprint $table) {
                if (!Schema::hasColumn('organization_plans', 'uuid')) $table->uuid('uuid')->nullable()->unique()->after('id');
                if (!Schema::hasColumn('organization_plans', 'plan_name')) $table->string('plan_name')->after('organization_id');
                if (!Schema::hasColumn('organization_plans', 'max_users')) $table->integer('max_users')->default(5)->after('plan_name');
                if (!Schema::hasColumn('organization_plans', 'max_modules')) $table->integer('max_modules')->default(3)->after('max_users');
                if (!Schema::hasColumn('organization_plans', 'deleted_at')) $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_plans');
    }
};
