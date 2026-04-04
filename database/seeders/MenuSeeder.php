<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Submenu;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('slug', 'demo-corp')->firstOrFail();

        $adminRole = Role::where('organization_id', $org->id)->where('slug', 'org-admin')->firstOrFail();
        $managerRole = Role::where('organization_id', $org->id)->where('slug', 'manager')->firstOrFail();
        $employeeRole = Role::where('organization_id', $org->id)->where('slug', 'employee')->firstOrFail();

        // ── Seed Admin Module Menus ───────────────────────────────────────────
        $adminModule = Module::where('slug', 'admin')->first();
        if ($adminModule) {
            $settingsMenu = Menu::firstOrCreate(
                ['module_id' => $adminModule->id, 'slug' => 'admin-settings'],
                [
                    'name' => 'Settings',
                    'icon' => 'settings',
                    'route' => 'admin.settings.index',
                    'url' => '/admin/settings',
                    'sort_order' => 1,
                    'is_active' => true,
                ]
            );

            $this->assignToRoles($settingsMenu, [$adminRole]);

            // Role Submenu
            $rolePerm = Permission::where('slug', 'admin.role.view')->first();
            $roleSubmenu = Submenu::firstOrCreate(
                ['menu_id' => $settingsMenu->id, 'slug' => 'admin-roles'],
                [
                    'name' => 'Roles & Permissions',
                    'icon' => 'shield',
                    'route' => 'admin.roles.index',
                    'url' => '/admin/roles',
                    'permission_id' => $rolePerm?->id,
                    'sort_order' => 1,
                    'is_active' => true,
                ]
            );
            $this->assignSubmenuToRoles($roleSubmenu, [$adminRole]);

            // User Submenu
            $userPerm = Permission::where('slug', 'admin.user.view')->first();
            $userSubmenu = Submenu::firstOrCreate(
                ['menu_id' => $settingsMenu->id, 'slug' => 'admin-users'],
                [
                    'name' => 'Users',
                    'icon' => 'users',
                    'route' => 'admin.users.index',
                    'url' => '/admin/users',
                    'permission_id' => $userPerm?->id,
                    'sort_order' => 2,
                    'is_active' => true,
                ]
            );
            $this->assignSubmenuToRoles($userSubmenu, [$adminRole]);
        }

        // ── Seed HRM Module Menus ─────────────────────────────────────────────
        $hrmModule = Module::where('slug', 'hrm')->first();
        if ($hrmModule) {
            $employeeMenu = Menu::firstOrCreate(
                ['module_id' => $hrmModule->id, 'slug' => 'hrm-employees'],
                [
                    'name' => 'Employees',
                    'icon' => 'users',
                    'route' => 'hrm.employees.index',
                    'url' => '/hrm/employees',
                    'sort_order' => 1,
                    'is_active' => true,
                ]
            );

            // Give managers and admins access, limit employees to their own profile (which could be another sub-menu, but for simplicity they get access to view)
            $this->assignToRoles($employeeMenu, [$adminRole, $managerRole, $employeeRole]);

            // Employee Directory Submenu
            $empViewPerm = Permission::where('slug', 'hrm.employee.view')->first();
            $empSubmenu = Submenu::firstOrCreate(
                ['menu_id' => $employeeMenu->id, 'slug' => 'hrm-employee-directory'],
                [
                    'name' => 'Directory',
                    'icon' => 'book',
                    'route' => 'hrm.employees.directory',
                    'url' => '/hrm/employees/directory',
                    'permission_id' => $empViewPerm?->id,
                    'sort_order' => 1,
                    'is_active' => true,
                ]
            );
            $this->assignSubmenuToRoles($empSubmenu, [$adminRole, $managerRole, $employeeRole]);

            // Attendance Menu
            $attendanceMenu = Menu::firstOrCreate(
                ['module_id' => $hrmModule->id, 'slug' => 'hrm-attendance'],
                [
                    'name' => 'Attendance',
                    'icon' => 'clock',
                    'route' => 'hrm.attendance.index',
                    'url' => '/hrm/attendance',
                    'sort_order' => 2,
                    'is_active' => true,
                ]
            );
            $this->assignToRoles($attendanceMenu, [$adminRole, $managerRole, $employeeRole]);
        }

        // ── Seed Sales Module Menus ───────────────────────────────────────────
        $salesModule = Module::where('slug', 'sales')->first();
        if ($salesModule) {
            $salesMenu = Menu::firstOrCreate(
                ['module_id' => $salesModule->id, 'slug' => 'sales-invoices'],
                [
                    'name' => 'Invoicing',
                    'icon' => 'file-text',
                    'route' => 'sales.invoices.index',
                    'url' => '/sales/invoices',
                    'sort_order' => 1,
                    'is_active' => true,
                ]
            );
            // Employees may not be in sales, but assuming demo
            $this->assignToRoles($salesMenu, [$adminRole, $managerRole]);
        }

        $this->command->info('Menus and Submenus seeded and linked to Roles & Permissions.');
    }

    private function assignToRoles(Menu $menu, array $roles): void
    {
        foreach ($roles as $role) {
            $role->menus()->syncWithoutDetaching([$menu->id => []]);
        }
    }

    private function assignSubmenuToRoles(Submenu $submenu, array $roles): void
    {
        foreach ($roles as $role) {
            $role->submenus()->syncWithoutDetaching([$submenu->id => []]);
        }
    }
}
