<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\Module;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $company = \App\Models\Company::where('slug', 'ati-limited')->first();
        $orgs = Organization::whereIn('slug', ['ati-tech', 'ati-services', 'demo-corp'])->get();
        $allModules = Module::all();
        $hrmModule = $allModules->firstWhere('slug', 'hrm');

        foreach ($orgs as $org) {
            // ── Create Roles for this Organization ────────────────────────────
            $superAdminRole = Role::firstOrCreate(
                ['organization_id' => $org->id, 'slug' => 'super-admin'],
                [
                    'company_id' => $company?->id,
                    'name' => 'Super Admin',
                    'description' => 'Full system access.',
                    'is_inheritable' => false,
                    'level' => 0,
                    'is_active' => true,
                ]
            );

            $adminRole = Role::firstOrCreate(
                ['organization_id' => $org->id, 'slug' => 'org-admin'],
                [
                    'company_id' => $company?->id,
                    'name' => 'Organization Admin',
                    'description' => 'Full access within the organization.',
                    'parent_role_id' => $superAdminRole->id,
                    'is_inheritable' => true,
                    'level' => 1,
                    'is_active' => true,
                ]
            );

            $employeeRole = Role::firstOrCreate(
                ['organization_id' => $org->id, 'slug' => 'employee'],
                [
                    'company_id' => $company?->id,
                    'name' => 'Employee',
                    'description' => 'Standard employee access.',
                    'is_inheritable' => true,
                    'level' => 3,
                    'is_active' => true,
                ]
            );

            // ── Assign Modules to Roles ───────────────────────────────────────
            $adminRole->modules()->syncWithoutDetaching($allModules->pluck('id')->toArray());
            if ($hrmModule) {
                $employeeRole->modules()->syncWithoutDetaching([$hrmModule->id]);
            }

            // ── Assign Roles to Users in this Org ─────────────────────────────
            $users = User::whereHas('organizations', function($q) use ($org) {
                $q->where('organizations.id', $org->id);
            })->get();

            foreach ($users as $user) {
                // If superadmin, give super-admin role
                if ($user->is_super_admin) {
                    $user->roles()->syncWithoutDetaching([
                        $superAdminRole->id => ['organization_id' => $org->id, 'company_id' => $company?->id]
                    ]);
                } 
                // If it's the admin user, give org-admin role
                elseif (strpos($user->email, 'admin@') !== false) {
                    $user->roles()->syncWithoutDetaching([
                        $adminRole->id => ['organization_id' => $org->id, 'company_id' => $company?->id]
                    ]);
                }
                // Otherwise give employee role
                else {
                    $user->roles()->syncWithoutDetaching([
                        $employeeRole->id => ['organization_id' => $org->id, 'company_id' => $company?->id]
                    ]);
                }
            }
        }

        $this->command->info('Roles and permissions assigned for all organizations.');
    }
}
