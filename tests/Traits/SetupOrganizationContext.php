<?php

namespace Tests\Traits;

use App\Models\Organization;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Laravel\Passport\Passport;

trait SetupOrganizationContext
{
    protected function setupOrgUser(string $roleName = 'Admin', array $permissions = []): array
    {
        \Illuminate\Support\Facades\Cache::flush();
        $company = \App\Models\Company::factory()->create();
        $org = Organization::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'organization_id' => $org->id,
            'is_super_admin' => false
        ]);

        $user->organizations()->attach($org->id, [
            'is_primary' => true,
            'is_owner' => false,
            'status' => 'active'
        ]);

        $role = Role::factory()->create([
            'company_id' => $company->id,
            'organization_id' => $org->id,
            'name' => $roleName,
            'slug' => strtolower($roleName)
        ]);

        $user->roles()->attach($role->id, ['organization_id' => $org->id, 'company_id' => $company->id]);

        $module = \App\Models\Module::firstOrCreate(['slug' => 'hrm'], [
            'name' => 'HRM',
            'uuid' => (string) \Illuminate\Support\Str::uuid()
        ]);

        if (!empty($permissions)) {
            foreach ($permissions as $slug) {
                $permission = Permission::firstOrCreate(['slug' => $slug], [
                    'name' => ucwords(str_replace('.', ' ', $slug)),
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'module_id' => $module->id
                ]);

                $role->permissions()->attach($permission->id, ['is_granted' => true]);
            }
        }

        return [
            'org' => $org,
            'user' => $user,
            'role' => $role,
            'company' => $company
        ];
    }

    protected function actingAsOrgMember($user, Organization $org)
    {
        Passport::actingAs($user);
        return $this->withHeaders(['X-ORG-ID' => $org->uuid]);
    }
}
