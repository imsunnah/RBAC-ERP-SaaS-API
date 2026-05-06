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
        $orgs = Organization::all();

        // ── Admin Module Menus ───────────────────────────────────────────
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

            // Submenus
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

            foreach ($orgs as $org) {
                $adminRole = Role::where('organization_id', $org->id)->where('slug', 'org-admin')->first();
                if ($adminRole) {
                    $this->assignToRoles($settingsMenu, [$adminRole]);
                    $this->assignSubmenuToRoles($roleSubmenu, [$adminRole]);
                    $this->assignSubmenuToRoles($userSubmenu, [$adminRole]);
                }
            }
        }

        // ── HRM Module Menus ─────────────────────────────────────────────
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

            foreach ($orgs as $org) {
                $adminRole = Role::where('organization_id', $org->id)->where('slug', 'org-admin')->first();
                $employeeRole = Role::where('organization_id', $org->id)->where('slug', 'employee')->first();
                
                if ($adminRole) {
                    $this->assignToRoles($employeeMenu, [$adminRole]);
                    $this->assignSubmenuToRoles($empSubmenu, [$adminRole]);
                }
                if ($employeeRole) {
                    $this->assignToRoles($employeeMenu, [$employeeRole]);
                    $this->assignSubmenuToRoles($empSubmenu, [$employeeRole]);
                }
            }
        }

        $this->command->info('Menus and Submenus seeded and linked to Roles across all organizations.');
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
