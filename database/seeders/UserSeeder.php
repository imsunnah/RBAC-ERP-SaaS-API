<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use App\Models\UserOrganization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $company = \App\Models\Company::where('slug', 'ati-limited')->first();
        $techOrg = Organization::where('slug', 'ati-tech')->firstOrFail();
        $servOrg = Organization::where('slug', 'ati-services')->firstOrFail();
        $demoOrg = Organization::where('slug', 'demo-corp')->firstOrFail();

        // ── Existing Admin Users ──────────────────────────────────────────────
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@atilimited.net'],
            [
                'company_id' => $company?->id,
                'organization_id' => $demoOrg->id,
                'name' => 'Super Admin',
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'password' => Hash::make('Password@123'),
                'is_super_admin' => true,
                'status' => 'active',
            ]
        );

        UserOrganization::firstOrCreate(
            ['user_id' => $superAdmin->id, 'organization_id' => $demoOrg->id],
            ['is_owner' => true, 'is_primary' => true, 'status' => 'active', 'joined_at' => now()]
        );

        // ── Ati Tech Employees (2) ─────────────────────────────────────────────
        foreach (range(1, 2) as $i) {
            $user = User::firstOrCreate(
                ['email' => "tech.emp$i@atilimited.net"],
                [
                    'company_id' => $company?->id,
                    'organization_id' => $techOrg->id,
                    'name' => "Tech Employee $i",
                    'first_name' => 'Tech',
                    'last_name' => "Employee $i",
                    'password' => Hash::make('Password@123'),
                    'is_super_admin' => false,
                    'status' => 'active',
                ]
            );

            UserOrganization::firstOrCreate(
                ['user_id' => $user->id, 'organization_id' => $techOrg->id],
                ['is_owner' => false, 'is_primary' => true, 'status' => 'active', 'joined_at' => now()]
            );
        }

        // ── Ati Services Employees (3) ─────────────────────────────────────────
        foreach (range(1, 3) as $i) {
            $user = User::firstOrCreate(
                ['email' => "serv.emp$i@atilimited.net"],
                [
                    'company_id' => $company?->id,
                    'organization_id' => $servOrg->id,
                    'name' => "Service Employee $i",
                    'first_name' => 'Service',
                    'last_name' => "Employee $i",
                    'password' => Hash::make('Password@123'),
                    'is_super_admin' => false,
                    'status' => 'active',
                ]
            );

            UserOrganization::firstOrCreate(
                ['user_id' => $user->id, 'organization_id' => $servOrg->id],
                ['is_owner' => false, 'is_primary' => true, 'status' => 'active', 'joined_at' => now()]
            );
        }

        // Keep old ones for backward compatibility
        $admin = User::firstOrCreate(
            ['email' => 'admin@atilimited.net'],
            [
                'company_id' => $company?->id,
                'organization_id' => $demoOrg->id,
                'name' => 'Org Admin',
                'first_name' => 'Org',
                'last_name' => 'Admin',
                'password' => Hash::make('Password@123'),
                'is_super_admin' => false,
                'status' => 'active',
            ]
        );
        UserOrganization::firstOrCreate(
            ['user_id' => $admin->id, 'organization_id' => $demoOrg->id],
            ['is_owner' => false, 'is_primary' => true, 'status' => 'active', 'joined_at' => now()]
        );

        $this->command->info('Users and Organizations seeded successfully with organization_id.');
    }
}
