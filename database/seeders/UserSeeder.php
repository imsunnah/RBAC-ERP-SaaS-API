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
        $org = Organization::where('slug', 'demo-corp')->firstOrFail();

        // ── Super Admin ───────────────────────────────────────────────────────
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@erp.test'],
            [
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
            ['email' => 'admin@demo-corp.test'],
            [
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
            ['email' => 'employee@demo-corp.test'],
            [
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
        $this->command->info('  superadmin@erp.test    / Password@123  (Super Admin)');
        $this->command->info('  admin@demo-corp.test   / Password@123  (Org Admin)');
        $this->command->info('  employee@demo-corp.test / Password@123 (Employee)');
    }
}
