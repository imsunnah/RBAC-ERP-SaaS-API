<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Seed permissions for metadata CRUD routes and insert default lookup data.
 */
return new class extends Migration {
    public function up(): void
    {
        $adminModuleId = $this->resolveModule('Admin', 'admin');

        // ── Permission definitions ─────────────────────────────────────
        $permissions = [
            // Department
            ['module_id' => $adminModuleId, 'name' => 'View Departments',   'slug' => 'admin.department.view',    'group' => 'admin.department'],
            ['module_id' => $adminModuleId, 'name' => 'Create Department',  'slug' => 'admin.department.create',  'group' => 'admin.department'],
            ['module_id' => $adminModuleId, 'name' => 'Update Department',  'slug' => 'admin.department.update',  'group' => 'admin.department'],
            ['module_id' => $adminModuleId, 'name' => 'Delete Department',  'slug' => 'admin.department.delete',  'group' => 'admin.department'],

            // Designation
            ['module_id' => $adminModuleId, 'name' => 'View Designations',   'slug' => 'admin.designation.view',    'group' => 'admin.designation'],
            ['module_id' => $adminModuleId, 'name' => 'Create Designation',  'slug' => 'admin.designation.create',  'group' => 'admin.designation'],
            ['module_id' => $adminModuleId, 'name' => 'Update Designation',  'slug' => 'admin.designation.update',  'group' => 'admin.designation'],
            ['module_id' => $adminModuleId, 'name' => 'Delete Designation',  'slug' => 'admin.designation.delete',  'group' => 'admin.designation'],

            // Gender
            ['module_id' => $adminModuleId, 'name' => 'View Genders',   'slug' => 'admin.gender.view',    'group' => 'admin.gender'],
            ['module_id' => $adminModuleId, 'name' => 'Create Gender',  'slug' => 'admin.gender.create',  'group' => 'admin.gender'],
            ['module_id' => $adminModuleId, 'name' => 'Update Gender',  'slug' => 'admin.gender.update',  'group' => 'admin.gender'],
            ['module_id' => $adminModuleId, 'name' => 'Delete Gender',  'slug' => 'admin.gender.delete',  'group' => 'admin.gender'],

            // Religion
            ['module_id' => $adminModuleId, 'name' => 'View Religions',   'slug' => 'admin.religion.view',    'group' => 'admin.religion'],
            ['module_id' => $adminModuleId, 'name' => 'Create Religion',  'slug' => 'admin.religion.create',  'group' => 'admin.religion'],
            ['module_id' => $adminModuleId, 'name' => 'Update Religion',  'slug' => 'admin.religion.update',  'group' => 'admin.religion'],
            ['module_id' => $adminModuleId, 'name' => 'Delete Religion',  'slug' => 'admin.religion.delete',  'group' => 'admin.religion'],

            // Marital Status
            ['module_id' => $adminModuleId, 'name' => 'View Marital Statuses',   'slug' => 'admin.marital-status.view',    'group' => 'admin.marital-status'],
            ['module_id' => $adminModuleId, 'name' => 'Create Marital Status',   'slug' => 'admin.marital-status.create',  'group' => 'admin.marital-status'],
            ['module_id' => $adminModuleId, 'name' => 'Update Marital Status',   'slug' => 'admin.marital-status.update',  'group' => 'admin.marital-status'],
            ['module_id' => $adminModuleId, 'name' => 'Delete Marital Status',   'slug' => 'admin.marital-status.delete',  'group' => 'admin.marital-status'],
        ];

        foreach ($permissions as $perm) {
            if (DB::table('permissions')->where('slug', $perm['slug'])->exists()) {
                continue;
            }

            DB::table('permissions')->insert(array_merge($perm, [
                'uuid'       => Str::uuid()->toString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // ── Seed default lookup data ───────────────────────────────────
        // Guard with hasTable checks — these legacy tables may not exist
        // in fresh databases (they were dropped by a later migration).

        // Genders
        if (Schema::hasTable('genders')) {
            $genders = ['Male', 'Female', 'Other'];
            foreach ($genders as $name) {
                if (!DB::table('genders')->where('slug', Str::slug($name))->exists()) {
                    DB::table('genders')->insert([
                        'uuid'       => Str::uuid()->toString(),
                        'name'       => $name,
                        'slug'       => Str::slug($name),
                        'is_active'  => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Religions
        if (Schema::hasTable('religions')) {
            $religions = ['Islam', 'Hinduism', 'Christianity', 'Buddhism', 'Other'];
            foreach ($religions as $name) {
                if (!DB::table('religions')->where('slug', Str::slug($name))->exists()) {
                    DB::table('religions')->insert([
                        'uuid'       => Str::uuid()->toString(),
                        'name'       => $name,
                        'slug'       => Str::slug($name),
                        'is_active'  => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Marital Statuses
        if (Schema::hasTable('marital_statuses')) {
            $statuses = ['Single', 'Married', 'Divorced', 'Widowed'];
            foreach ($statuses as $name) {
                if (!DB::table('marital_statuses')->where('slug', Str::slug($name))->exists()) {
                    DB::table('marital_statuses')->insert([
                        'uuid'       => Str::uuid()->toString(),
                        'name'       => $name,
                        'slug'       => Str::slug($name),
                        'is_active'  => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $slugs = [
            'admin.department.view',
            'admin.department.create',
            'admin.department.update',
            'admin.department.delete',
            'admin.designation.view',
            'admin.designation.create',
            'admin.designation.update',
            'admin.designation.delete',
            'admin.gender.view',
            'admin.gender.create',
            'admin.gender.update',
            'admin.gender.delete',
            'admin.religion.view',
            'admin.religion.create',
            'admin.religion.update',
            'admin.religion.delete',
            'admin.marital-status.view',
            'admin.marital-status.create',
            'admin.marital-status.update',
            'admin.marital-status.delete',
        ];

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->whereIn('slug', $slugs)->delete();
        }
        if (Schema::hasTable('genders')) {
            DB::table('genders')->truncate();
        }
        if (Schema::hasTable('religions')) {
            DB::table('religions')->truncate();
        }
        if (Schema::hasTable('marital_statuses')) {
            DB::table('marital_statuses')->truncate();
        }
    }

    private function resolveModule(string $name, string $slug): int
    {
        $module = DB::table('modules')->where('slug', $slug)->first();
        if ($module) return $module->id;

        return DB::table('modules')->insertGetId([
            'uuid'       => Str::uuid()->toString(),
            'name'       => $name,
            'slug'       => $slug,
            'icon'       => 'settings',
            'is_active'  => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
