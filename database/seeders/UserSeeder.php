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
        $company = \App\Models\Company::first();
        $org = Organization::where('slug', 'demo-corp')->firstOrFail();

        // ── Super Admin ───────────────────────────────────────────────────────
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@atilimited.net'],
            [
                'company_id' => $company?->id,
                'name' => 'Super Admin',
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'password' => Hash::make('Password@123'),
                'is_super_admin' => true,
                'status' => 'active',
            ]
        );

        UserOrganization::firstOrCreate(
            ['user_id' => $superAdmin->id, 'organization_id' => $org->id],
            ['is_owner' => true, 'is_primary' => true, 'status' => 'active', 'joined_at' => now()]
        );

        // ── Org Admin ─────────────────────────────────────────────────────────
        $admin = User::firstOrCreate(
            ['email' => 'admin@atilimited.net'],
            [
                'company_id' => $company?->id,
                'name' => 'Org Admin',
                'first_name' => 'Org',
                'last_name' => 'Admin',
                'password' => Hash::make('Password@123'),
                'is_super_admin' => false,
                'status' => 'active',
            ]
        );

        UserOrganization::firstOrCreate(
            ['user_id' => $admin->id, 'organization_id' => $org->id],
            ['is_owner' => false, 'is_primary' => true, 'status' => 'active', 'joined_at' => now()]
        );

        // ── Regular Employee ──────────────────────────────────────────────────
        $employee = User::firstOrCreate(
            ['email' => 'employee@atilimited.net'],
            [
                'company_id' => $company?->id,
                'name' => 'John Employee',
                'first_name' => 'John',
                'last_name' => 'Employee',
                'password' => Hash::make('Password@123'),
                'is_super_admin' => false,
                'status' => 'active',
            ]
        );

        UserOrganization::firstOrCreate(
            ['user_id' => $employee->id, 'organization_id' => $org->id],
            ['is_owner' => false, 'is_primary' => true, 'status' => 'active', 'joined_at' => now()]
        );

        $this->command->info('Users seeded:');
        $this->command->info('  superadmin@atilimited.net    / Password@123  (Super Admin)');
        $this->command->info('  admin@atilimited.net   / Password@123  (Org Admin)');
        $this->command->info('  employee@atilimited.net / Password@123 (Employee)');
    }
}
