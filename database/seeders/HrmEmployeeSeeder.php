<?php

namespace Database\Seeders;

use App\Models\HrmEmployee;
use App\Models\User;
use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class HrmEmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::where('slug', 'ati-limited')->first();
        $users = User::where('email', 'like', '%@atilimited.net')->get();

        foreach ($users as $user) {
            HrmEmployee::firstOrCreate(
                ['office_email' => $user->email],
                [
                    'company_id' => $user->company_id ?? $company?->id,
                    'organization_id' => $user->organization_id, // Link to user's primary org
                    'full_name' => $user->name,
                    'office_email' => $user->email,
                    'personal_email' => $user->email,
                    'is_active' => true,
                    'user_employee_id' => substr((string) $user->id, 0, 16),
                    'created_by' => $user->id,
                ]
            );
        }
    }
}
