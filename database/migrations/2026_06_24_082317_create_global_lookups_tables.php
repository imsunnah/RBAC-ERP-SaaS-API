<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create global_lookup_groups table
        if (!Schema::hasTable('global_lookup_groups')) {
            Schema::create('global_lookup_groups', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('name');
                $table->string('slug');
                $table->foreignId('organization_id')->nullable()->constrained('organizations')->onDelete('cascade');
                $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade');
                $table->boolean('is_active')->default(true);
                $table->softDeletes();
                $table->timestamps();

                $table->unique(['organization_id', 'slug'], 'global_lookup_groups_org_slug_unique');
            });
        }

        // 2. Create global_lookups table
        if (!Schema::hasTable('global_lookups')) {
            Schema::create('global_lookups', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('global_lookup_group_id')->constrained('global_lookup_groups')->onDelete('cascade');
                $table->string('name');
                $table->string('slug');
                $table->foreignId('organization_id')->nullable()->constrained('organizations')->onDelete('cascade');
                $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade');
                $table->boolean('is_active')->default(true);
                $table->softDeletes();
                $table->timestamps();

                $table->unique(['global_lookup_group_id', 'slug'], 'global_lookups_group_slug_unique');
            });
        }

        // 3. Migrate data and Seed
        $groups = [
            'Gender' => 'genders',
            'Religion' => 'religions',
            'Marital Status' => 'marital_statuses',
        ];

        foreach ($groups as $groupName => $oldTable) {
            if (Schema::hasTable($oldTable)) {
                $groupId = DB::table('global_lookup_groups')
                    ->where('slug', Str::slug($groupName))
                    ->whereNull('organization_id')
                    ->value('id');

                if (!$groupId) {
                    $groupId = DB::table('global_lookup_groups')->insertGetId([
                        'uuid' => (string) Str::uuid(),
                        'name' => $groupName,
                        'slug' => Str::slug($groupName),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $oldData = DB::table($oldTable)->get();
                foreach ($oldData as $item) {
                    $exists = DB::table('global_lookups')
                        ->where('global_lookup_group_id', $groupId)
                        ->where('slug', $item->slug)
                        ->exists();

                    if (!$exists) {
                        DB::table('global_lookups')->insert([
                            'uuid' => $item->uuid ?? (string) Str::uuid(),
                            'global_lookup_group_id' => $groupId,
                            'name' => $item->name,
                            'slug' => $item->slug,
                            'is_active' => $item->is_active ?? true,
                            'created_at' => $item->created_at ?? now(),
                            'updated_at' => $item->updated_at ?? now(),
                        ]);
                    }
                }
            }
        }

        // 4. Update hrm_employees table columns - safer approach
        // We add new columns first, then we can drop old ones later.
        Schema::table('hrm_employees', function (Blueprint $table) {
            if (!Schema::hasColumn('hrm_employees', 'gender_uuid')) {
                $table->uuid('gender_uuid')->nullable()->after('employee_name_local');
            }
            if (!Schema::hasColumn('hrm_employees', 'religion_uuid')) {
                $table->uuid('religion_uuid')->nullable()->after('blood_group_id');
            }
            if (!Schema::hasColumn('hrm_employees', 'marital_status_uuid')) {
                $table->uuid('marital_status_uuid')->nullable()->after('religion_id');
            }
        });

        // 5. Drop old tables safely
        // In a real migration we might want to keep them until everything is verified.
        // But the user said "remove the table api all".
        Schema::dropIfExists('genders');
        Schema::dropIfExists('religions');
        Schema::dropIfExists('marital_statuses');
    }

    public function down(): void
    {
        Schema::dropIfExists('global_lookups');
        Schema::dropIfExists('global_lookup_groups');
    }
};
