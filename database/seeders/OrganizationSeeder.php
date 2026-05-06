<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Module;
use App\Models\OrganizationPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $company = \App\Models\Company::where('slug', 'ati-limited')->first();

        $orgs = [
            [
                'name' => 'Ati Tech',
                'slug' => 'ati-tech',
                'timezone' => 'Asia/Dhaka',
                'currency' => 'BDT',
                'locale' => 'en',
                'status' => 'active',
            ],
            [
                'name' => 'Ati Services',
                'slug' => 'ati-services',
                'timezone' => 'Asia/Dhaka',
                'currency' => 'BDT',
                'locale' => 'en',
                'status' => 'active',
            ],
        ];

        foreach ($orgs as $orgData) {
            $org = Organization::firstOrCreate(
                ['slug' => $orgData['slug']],
                array_merge($orgData, ['company_id' => $company?->id])
            );

            // ── Billing plan ──────────────────────────────────────────────────────
            OrganizationPlan::firstOrCreate(
                ['organization_id' => $org->id],
                [
                    'plan_name' => 'Professional',
                    'max_users' => 50,
                    'max_modules' => 8,
                    'billing_cycle' => 'monthly',
                    'status' => 'trial',
                    'amount' => 49.99,
                    'currency' => 'USD',
                    'trial_ends_at' => now()->addDays(30),
                    'starts_at' => now(),
                    'expires_at' => now()->addYear(),
                ]
            );

            // ── Activate modules for this organization ────────────────────────────
            $coreModules = Module::where('is_core', true)->get();
            $extraModules = Module::where('is_core', false)->take(3)->get();
            $allModules = $coreModules->merge($extraModules);

            foreach ($allModules as $module) {
                $org->modules()->syncWithoutDetaching([
                    $module->id => [
                        'is_active' => true,
                        'activated_at' => now(),
                        'expires_at' => null,
                    ],
                ]);
            }
        }

        // Keep the old demo-corp for backward compatibility if needed, or just focus on the new ones
        $demoOrg = Organization::firstOrCreate(
            ['slug' => 'demo-corp'],
            [
                'company_id' => $company?->id,
                'name' => 'Demo Corporation',
                'slug' => 'demo-corp',
                'timezone' => 'Asia/Dhaka',
                'currency' => 'BDT',
                'locale' => 'en',
                'status' => 'active',
            ]
        );
    }
}
