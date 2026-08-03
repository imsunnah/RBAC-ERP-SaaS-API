<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return config('telescope.storage.database.connection');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('telescope_entries_tags');
        Schema::dropIfExists('telescope_entries');
        Schema::dropIfExists('telescope_monitoring');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
