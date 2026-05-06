<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = [
            [
                'name' => 'Ati Limited',
                'slug' => 'ati-limited',
                'domain' => 'atilimited.net',
                'status' => 'active',
            ],
            [
                'name' => 'Demo Group',
                'slug' => 'demo-group',
                'domain' => 'demo-group.com',
                'status' => 'active',
            ],
        ];

        foreach ($companies as $company) {
            Company::firstOrCreate(
                ['slug' => $company['slug']],
                $company
            );
        }
    }
}
