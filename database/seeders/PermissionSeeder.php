<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    private array $permissions = [
        'admin' => [
            ['name' => 'View Roles', 'slug' => 'admin.role.view', 'group' => 'admin.role'],
            ['name' => 'Create Role', 'slug' => 'admin.role.create', 'group' => 'admin.role'],
            ['name' => 'Update Role', 'slug' => 'admin.role.update', 'group' => 'admin.role'],
            ['name' => 'Delete Role', 'slug' => 'admin.role.delete', 'group' => 'admin.role'],
            ['name' => 'View Users', 'slug' => 'admin.user.view', 'group' => 'admin.user'],
            ['name' => 'Create User', 'slug' => 'admin.user.create', 'group' => 'admin.user'],
            ['name' => 'Update User', 'slug' => 'admin.user.update', 'group' => 'admin.user'],
            ['name' => 'Delete User', 'slug' => 'admin.user.delete', 'group' => 'admin.user'],
            ['name' => 'View Permissions', 'slug' => 'admin.permission.view', 'group' => 'admin.permission'],
            ['name' => 'Assign Permission', 'slug' => 'admin.permission.assign', 'group' => 'admin.permission'],
            ['name' => 'View Modules', 'slug' => 'admin.module.view', 'group' => 'admin.module'],
            ['name' => 'Manage Modules', 'slug' => 'admin.module.manage', 'group' => 'admin.module'],
        ],
        'hrm' => [
            ['name' => 'View Employees', 'slug' => 'hrm.employee.view', 'group' => 'hrm.employee'],
            ['name' => 'Create Employee', 'slug' => 'hrm.employee.create', 'group' => 'hrm.employee'],
            ['name' => 'Update Employee', 'slug' => 'hrm.employee.update', 'group' => 'hrm.employee'],
            ['name' => 'Delete Employee', 'slug' => 'hrm.employee.delete', 'group' => 'hrm.employee'],
            ['name' => 'View Attendance', 'slug' => 'hrm.attendance.view', 'group' => 'hrm.attendance'],
            ['name' => 'Manage Attendance', 'slug' => 'hrm.attendance.manage', 'group' => 'hrm.attendance'],
            ['name' => 'View Leave', 'slug' => 'hrm.leave.view', 'group' => 'hrm.leave'],
            ['name' => 'Approve Leave', 'slug' => 'hrm.leave.approve', 'group' => 'hrm.leave'],
            ['name' => 'View Payroll', 'slug' => 'hrm.payroll.view', 'group' => 'hrm.payroll'],
            ['name' => 'Process Payroll', 'slug' => 'hrm.payroll.process', 'group' => 'hrm.payroll'],
        ],
    ];

    public function run(): void
    {
        $orgs = Organization::all();

        foreach ($this->permissions as $moduleSlug => $perms) {
            $module = Module::where('slug', $moduleSlug)->first();
            if (!$module) continue;

            foreach ($perms as $permData) {
                $permission = Permission::firstOrCreate(
                    ['slug' => $permData['slug']],
                    [
                        'module_id' => $module->id,
                        'name' => $permData['name'],
                        'description' => $permData['name'] . ' in ' . $module->name,
                        'group' => $permData['group'],
                    ]
                );

                foreach ($orgs as $org) {
                    $adminRole = Role::where('organization_id', $org->id)->where('slug', 'org-admin')->first();
                    $employeeRole = Role::where('organization_id', $org->id)->where('slug', 'employee')->first();

                    if ($adminRole) {
                        $adminRole->permissions()->syncWithoutDetaching([$permission->id => ['is_granted' => true]]);
                    }

                    if ($employeeRole && str_ends_with($permData['slug'], '.view')) {
                        $employeeRole->permissions()->syncWithoutDetaching([$permission->id => ['is_granted' => true]]);
                    }
                }
            }
        }

        $this->command->info('Permissions seeded and assigned to roles across all organizations.');
    }
}
