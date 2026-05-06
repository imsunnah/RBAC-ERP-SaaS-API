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
        Schema::table('organizations', function (Blueprint $table) {
            $legacyColumns = [
                'organization_id', 'organization_name', 'organization_slug', 'organization_info',
                'organization_detail', 'organization_slogan', 'organization_abbr', 'address_info',
                'category_id', 'parent_id', 'phone_numbers', 'website', 'logo_url',
                'user_defined_sl_no', 'active_status', 'record_show_flag', 'display_image'
            ];

            foreach ($legacyColumns as $col) {
                if (Schema::hasColumn('organizations', $col)) {
                    // Using change() requires doctrine/dbal or Laravel 10+ native support
                    // Since this is Laravel 11/13, native support is there for most databases.
                    $table->string($col)->nullable()->change();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No easy way to undo nullable without knowing previous state, but we don't need it.
    }
};
