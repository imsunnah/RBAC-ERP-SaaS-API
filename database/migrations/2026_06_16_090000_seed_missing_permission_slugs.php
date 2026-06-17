<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seed permission slugs for all route-level permission checks.
 *
 * This migration inserts missing permission rows that the `permission:`
 * route middleware references. It finds or creates the module for each
 * group (admin, hrm) and inserts permissions idempotently.
 */
return new class extends Migration {
    public function up(): void
    {
        // ── Resolve or create module IDs ───────────────────────────────
        $adminModuleId = $this->resolveModule('Admin', 'admin');
        $hrmModuleId   = $this->resolveModule('HRM', 'hrm');

        // ── Permission definitions ─────────────────────────────────────
        $permissions = [
            // Admin — Module management
            ['module_id' => $adminModuleId, 'name' => 'View Modules',     'slug' => 'admin.module.view',     'group' => 'admin.module'],
            ['module_id' => $adminModuleId, 'name' => 'Create Module',    'slug' => 'admin.module.create',   'group' => 'admin.module'],
            ['module_id' => $adminModuleId, 'name' => 'Update Module',    'slug' => 'admin.module.update',   'group' => 'admin.module'],
            ['module_id' => $adminModuleId, 'name' => 'Delete Module',    'slug' => 'admin.module.delete',   'group' => 'admin.module'],

            // Admin — Menu management
            ['module_id' => $adminModuleId, 'name' => 'View Menus',      'slug' => 'admin.menu.view',       'group' => 'admin.menu'],
            ['module_id' => $adminModuleId, 'name' => 'Create Menu',     'slug' => 'admin.menu.create',     'group' => 'admin.menu'],
            ['module_id' => $adminModuleId, 'name' => 'Update Menu',     'slug' => 'admin.menu.update',     'group' => 'admin.menu'],
            ['module_id' => $adminModuleId, 'name' => 'Delete Menu',     'slug' => 'admin.menu.delete',     'group' => 'admin.menu'],

            // Admin — Submenu management
            ['module_id' => $adminModuleId, 'name' => 'View Submenus',   'slug' => 'admin.submenu.view',    'group' => 'admin.submenu'],
            ['module_id' => $adminModuleId, 'name' => 'Create Submenu',  'slug' => 'admin.submenu.create',  'group' => 'admin.submenu'],
            ['module_id' => $adminModuleId, 'name' => 'Update Submenu',  'slug' => 'admin.submenu.update',  'group' => 'admin.submenu'],
            ['module_id' => $adminModuleId, 'name' => 'Delete Submenu',  'slug' => 'admin.submenu.delete',  'group' => 'admin.submenu'],

            // Admin — Permission management (some may already exist)
            ['module_id' => $adminModuleId, 'name' => 'View Permissions',   'slug' => 'admin.permission.view',    'group' => 'admin.permission'],
            ['module_id' => $adminModuleId, 'name' => 'Create Permission',  'slug' => 'admin.permission.create',  'group' => 'admin.permission'],
            ['module_id' => $adminModuleId, 'name' => 'Update Permission',  'slug' => 'admin.permission.update',  'group' => 'admin.permission'],
            ['module_id' => $adminModuleId, 'name' => 'Delete Permission',  'slug' => 'admin.permission.delete',  'group' => 'admin.permission'],
            ['module_id' => $adminModuleId, 'name' => 'Assign Permissions', 'slug' => 'admin.permission.assign',  'group' => 'admin.permission'],

            // Admin — Role management (some may already exist)
            ['module_id' => $adminModuleId, 'name' => 'View Roles',     'slug' => 'admin.role.view',    'group' => 'admin.role'],
            ['module_id' => $adminModuleId, 'name' => 'Create Role',    'slug' => 'admin.role.create',  'group' => 'admin.role'],
            ['module_id' => $adminModuleId, 'name' => 'Update Role',    'slug' => 'admin.role.update',  'group' => 'admin.role'],
            ['module_id' => $adminModuleId, 'name' => 'Delete Role',    'slug' => 'admin.role.delete',  'group' => 'admin.role'],
            ['module_id' => $adminModuleId, 'name' => 'Assign Role',    'slug' => 'admin.role.assign',  'group' => 'admin.role'],

            // Admin — User management (some may already exist)
            ['module_id' => $adminModuleId, 'name' => 'View Users',     'slug' => 'admin.user.view',    'group' => 'admin.user'],
            ['module_id' => $adminModuleId, 'name' => 'Create User',    'slug' => 'admin.user.create',  'group' => 'admin.user'],
            ['module_id' => $adminModuleId, 'name' => 'Update User',    'slug' => 'admin.user.update',  'group' => 'admin.user'],
            ['module_id' => $adminModuleId, 'name' => 'Delete User',    'slug' => 'admin.user.delete',  'group' => 'admin.user'],

            // HRM — Employee management
            ['module_id' => $hrmModuleId, 'name' => 'View Employees',    'slug' => 'hrm.employee.view',    'group' => 'hrm.employee'],
            ['module_id' => $hrmModuleId, 'name' => 'Create Employee',   'slug' => 'hrm.employee.create',  'group' => 'hrm.employee'],
            ['module_id' => $hrmModuleId, 'name' => 'Update Employee',   'slug' => 'hrm.employee.update',  'group' => 'hrm.employee'],
            ['module_id' => $hrmModuleId, 'name' => 'Delete Employee',   'slug' => 'hrm.employee.delete',  'group' => 'hrm.employee'],
            ['module_id' => $hrmModuleId, 'name' => 'Export Employees',  'slug' => 'hrm.employee.export',  'group' => 'hrm.employee'],

            // HRM — Attendance management
            ['module_id' => $hrmModuleId, 'name' => 'View Attendance',    'slug' => 'hrm.attendance.view',    'group' => 'hrm.attendance'],
            ['module_id' => $hrmModuleId, 'name' => 'Export Attendance',   'slug' => 'hrm.attendance.export',  'group' => 'hrm.attendance'],

            // HRM — Device management
            ['module_id' => $hrmModuleId, 'name' => 'View Devices',   'slug' => 'hrm.device.view',    'group' => 'hrm.device'],
            ['module_id' => $hrmModuleId, 'name' => 'Update Device',  'slug' => 'hrm.device.update',  'group' => 'hrm.device'],
        ];

        foreach ($permissions as $perm) {
            // Idempotent — skip if slug already exists
            if (DB::table('permissions')->where('slug', $perm['slug'])->exists()) {
                continue;
            }

            DB::table('permissions')->insert(array_merge($perm, [
                'uuid'       => Str::uuid()->toString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        $slugs = [
            'admin.module.view',
            'admin.module.create',
            'admin.module.update',
            'admin.module.delete',
            'admin.menu.view',
            'admin.menu.create',
            'admin.menu.update',
            'admin.menu.delete',
            'admin.submenu.view',
            'admin.submenu.create',
            'admin.submenu.update',
            'admin.submenu.delete',
            'admin.permission.view',
            'admin.permission.create',
            'admin.permission.update',
            'admin.permission.delete',
            'admin.permission.assign',
            'admin.role.view',
            'admin.role.create',
            'admin.role.update',
            'admin.role.delete',
            'admin.role.assign',
            'admin.user.view',
            'admin.user.create',
            'admin.user.update',
            'admin.user.delete',
            'hrm.employee.view',
            'hrm.employee.create',
            'hrm.employee.update',
            'hrm.employee.delete',
            'hrm.employee.export',
            'hrm.attendance.view',
            'hrm.attendance.export',
            'hrm.device.view',
            'hrm.device.update',
        ];

        DB::table('permissions')->whereIn('slug', $slugs)->delete();
    }

    /**
     * Find a module by slug, or create it if missing.
     */
    private function resolveModule(string $name, string $slug): int
    {
        $module = DB::table('modules')->where('slug', $slug)->first();

        if ($module) {
            return $module->id;
        }

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
