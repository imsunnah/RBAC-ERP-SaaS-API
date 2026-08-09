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
        Schema::create('hrm_weekly_meals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade');
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->onDelete('cascade');
            
            $table->string('day'); // saturday, sunday, monday, tuesday, wednesday, thursday, friday
            $table->string('lunch_item');
            $table->string('alternative_lunch')->nullable();
            $table->integer('expected_person')->default(0);
            
            $table->foreignId('chef_id')->nullable()->constrained('hrm_employees')->onDelete('set null');
            $table->string('chef_name')->nullable();
            
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'day']);
            $table->index(['company_id', 'day']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hrm_weekly_meals');
    }
};
