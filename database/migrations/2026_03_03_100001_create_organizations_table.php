<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('organizations')) {
            Schema::create('organizations', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->nullable()->unique();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('logo')->nullable();
                $table->string('domain')->nullable()->unique();
                $table->string('timezone')->default('UTC');
                $table->string('currency', 10)->default('USD');
                $table->string('locale', 10)->default('en');
                $table->json('settings')->nullable();
                $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            Schema::table('organizations', function (Blueprint $table) {
                // Add new columns if missing
                if (!Schema::hasColumn('organizations', 'uuid')) $table->uuid('uuid')->nullable()->unique()->after('id');
                if (!Schema::hasColumn('organizations', 'name')) $table->string('name')->after('uuid');
                if (!Schema::hasColumn('organizations', 'slug')) $table->string('slug')->unique()->after('name');
                if (!Schema::hasColumn('organizations', 'logo')) $table->string('logo')->nullable()->after('slug');
                if (!Schema::hasColumn('organizations', 'domain')) $table->string('domain')->nullable()->unique()->after('logo');
                if (!Schema::hasColumn('organizations', 'timezone')) $table->string('timezone')->default('UTC')->after('domain');
                if (!Schema::hasColumn('organizations', 'currency')) $table->string('currency', 10)->default('USD')->after('timezone');
                if (!Schema::hasColumn('organizations', 'locale')) $table->string('locale', 10)->default('en')->after('currency');
                if (!Schema::hasColumn('organizations', 'settings')) $table->json('settings')->nullable()->after('locale');
                if (!Schema::hasColumn('organizations', 'status')) $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->after('settings');
                if (!Schema::hasColumn('organizations', 'deleted_at')) $table->softDeletes();
                if (!Schema::hasColumn('organizations', 'company_id')) $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade');

                // Make legacy columns nullable to avoid SEEDER failures
                $legacyColumns = [
                    'organization_id', 'organization_name', 'organization_slug', 'organization_info',
                    'organization_detail', 'organization_slogan', 'organization_abbr', 'address_info',
                    'category_id', 'parent_id', 'phone_numbers', 'website', 'logo_url',
                    'user_defined_sl_no', 'active_status', 'record_show_flag', 'display_image'
                ];

                foreach ($legacyColumns as $col) {
                    if (Schema::hasColumn('organizations', $col)) {
                        $table->string($col)->nullable()->change();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
