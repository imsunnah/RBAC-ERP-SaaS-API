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
        $company = \App\Models\Company::first();

        // ── Seed a demo organization ──────────────────────────────────────────
        $org = Organization::firstOrCreate(
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
}
