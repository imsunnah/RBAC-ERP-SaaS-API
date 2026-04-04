<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            [
                'name' => 'Administration',
                'slug' => 'admin',
                'description' => 'System administration, roles, users, and settings.',
                'icon' => 'shield-check',
                'is_core' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Human Resource Management',
                'slug' => 'hrm',
                'description' => 'Employee lifecycle, attendance, payroll, and leave management.',
                'icon' => 'users',
                'is_core' => false,
                'sort_order' => 2,
            ],
            [
                'name' => 'Sales',
                'slug' => 'sales',
                'description' => 'Customers, quotations, orders, and invoices.',
                'icon' => 'trending-up',
                'is_core' => false,
                'sort_order' => 3,
            ],
            [
                'name' => 'Inventory',
                'slug' => 'inventory',
                'description' => 'Products, warehouses, stock, and movements.',
                'icon' => 'package',
                'is_core' => false,
                'sort_order' => 4,
            ],
            [
                'name' => 'Finance',
                'slug' => 'finance',
                'description' => 'Accounts, ledgers, vouchers, and financial reports.',
                'icon' => 'dollar-sign',
                'is_core' => false,
                'sort_order' => 5,
            ],
            [
                'name' => 'Procurement',
                'slug' => 'procurement',
                'description' => 'Vendors, purchase orders, and goods receipt.',
                'icon' => 'shopping-cart',
                'is_core' => false,
                'sort_order' => 6,
            ],
            [
                'name' => 'CRM',
                'slug' => 'crm',
                'description' => 'Leads, contacts, opportunities, and pipelines.',
                'icon' => 'phone-call',
                'is_core' => false,
                'sort_order' => 7,
            ],
            [
                'name' => 'Projects',
                'slug' => 'projects',
                'description' => 'Project planning, tasks, and timesheets.',
                'icon' => 'briefcase',
                'is_core' => false,
                'sort_order' => 8,
            ],
        ];

        foreach ($modules as $module) {
            Module::firstOrCreate(['slug' => $module['slug']], array_merge($module, ['is_active' => true]));
        }
    }
}
