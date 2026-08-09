<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CompanySeeder::class,
            ModuleSeeder::class,
            OrganizationSeeder::class,
            UserSeeder::class,
            HrmEmployeeSeeder::class,
            RoleSeeder::class,
            PermissionSeeder::class,
            MenuSeeder::class,
            HrmAttendanceSeeder::class,
        ]);
    }
}
