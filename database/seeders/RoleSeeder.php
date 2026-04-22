<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('slug', 'demo-corp')->firstOrFail();
        $admin = User::where('email', 'admin@demo-corp.test')->firstOrFail();
        $employee = User::where('email', 'employee@demo-corp.test')->firstOrFail();

        // ── Role hierarchy: Super Admin > Admin > Manager > Employee ──────────

        $superAdminRole = Role::firstOrCreate(
            ['organization_id' => $org->id, 'slug' => 'super-admin'],
            [
                'name' => 'Super Admin',
                'description' => 'Full system access. No restrictions.',
                'is_inheritable' => false,
                'level' => 0,
                'is_active' => true,
            ]
        );

        $adminRole = Role::firstOrCreate(
            ['organization_id' => $org->id, 'slug' => 'org-admin'],
            [
                'name' => 'Organization Admin',
                'description' => 'Full access within the organization.',
                'parent_role_id' => $superAdminRole->id,
                'is_inheritable' => true,
                'level' => 1,
                'is_active' => true,
            ]
        );

        $managerRole = Role::firstOrCreate(
            ['organization_id' => $org->id, 'slug' => 'manager'],
            [
                'name' => 'Manager',
                'description' => 'Can manage teams and approve requests.',
                'parent_role_id' => $adminRole->id,
                'is_inheritable' => true,
                'level' => 2,
                'is_active' => true,
            ]
        );

        $employeeRole = Role::firstOrCreate(
            ['organization_id' => $org->id, 'slug' => 'employee'],
            [
                'name' => 'Employee',
                'description' => 'Standard employee access.',
                'parent_role_id' => $managerRole->id,
                'is_inheritable' => true,
                'level' => 3,
                'is_active' => true,
            ]
        );

        // ── Assign roles to users ─────────────────────────────────────────────
        // admin user → Org Admin role
        if (!$admin->roles()->wherePivot('organization_id', $org->id)->wherePivot('role_id', $adminRole->id)->exists()) {
            $admin->roles()->attach($adminRole->id, ['organization_id' => $org->id]);
        }

        // employee user → Employee role
        if (!$employee->roles()->wherePivot('organization_id', $org->id)->wherePivot('role_id', $employeeRole->id)->exists()) {
            $employee->roles()->attach($employeeRole->id, ['organization_id' => $org->id]);
        }

        // ── Assign modules to roles via role_modules ──────────────────────────
        $allModules = \App\Models\Module::all();
        $adminModule = $allModules->firstWhere('slug', 'admin');
        $nonAdminModules = $allModules->where('slug', '!=', 'admin');

        // Org Admin → ALL modules (including admin/GAC)
        $adminRole->modules()->syncWithoutDetaching($allModules->pluck('id')->toArray());

        // Manager → All modules EXCEPT admin
        $managerRole->modules()->syncWithoutDetaching($nonAdminModules->pluck('id')->toArray());

        // Employee → Only non-admin modules they'd typically use (HRM)
        $hrmModule = $allModules->firstWhere('slug', 'hrm');
        if ($hrmModule) {
            $employeeRole->modules()->syncWithoutDetaching([$hrmModule->id]);
        }

        $this->command->info('Roles seeded: Super Admin > Org Admin > Manager > Employee');
        $this->command->info('  ├─ Org Admin: all modules assigned');
        $this->command->info('  ├─ Manager: non-admin modules assigned');
        $this->command->info('  └─ Employee: HRM module assigned');
    }
}
