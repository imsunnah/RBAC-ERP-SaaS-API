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
                'name'        => 'Administration',
                'slug'        => 'admin',
                'description' => 'System administration, roles, users, and settings.',
                'icon'        => 'shield-check',
                'is_core'     => true,
                'sort_order'  => 1,
            ],
            [
                'name'        => 'Human Resource Management',
                'slug'        => 'hrm',
                'description' => 'Employee lifecycle, attendance, and leave management.',
                'icon'        => 'users',
                'is_core'     => false,
                'sort_order'  => 2,
            ],
            [
                'name'        => 'Payroll',
                'slug'        => 'payroll',
                'description' => 'Automated salary processing and statutory compliance.',
                'icon'        => 'dollar-sign',
                'is_core'     => false,
                'sort_order'  => 3,
            ],
            [
                'name'        => 'Sales & CRM',
                'slug'        => 'sales',
                'description' => 'Customers, quotations, orders, and invoices.',
                'icon'        => 'trending-up',
                'is_core'     => false,
                'sort_order'  => 4,
            ],
            [
                'name'        => 'Inventory',
                'slug'        => 'inventory',
                'description' => 'Products, warehouses, stock, and movements.',
                'icon'        => 'package',
                'is_core'     => false,
                'sort_order'  => 5,
            ],
            [
                'name'        => 'Finance',
                'slug'        => 'finance',
                'description' => 'Accounts, ledgers, vouchers, and financial reports.',
                'icon'        => 'dollar-sign',
                'is_core'     => false,
                'sort_order'  => 6,
            ],
            [
                'name'        => 'Procurement',
                'slug'        => 'procurement',
                'description' => 'Vendors, purchase orders, and goods receipt.',
                'icon'        => 'shopping-cart',
                'is_core'     => false,
                'sort_order'  => 7,
            ],
            [
                'name'        => 'Manufacturing',
                'slug'        => 'mrp',
                'description' => 'Bill of materials and production scheduling.',
                'icon'        => 'settings',
                'is_core'     => false,
                'sort_order'  => 8,
            ],
            [
                'name'        => 'Meeting Room',
                'slug'        => 'meetings',
                'description' => 'Schedule rooms and track meeting minutes.',
                'icon'        => 'calendar',
                'is_core'     => false,
                'sort_order'  => 9,
            ],
            [
                'name'        => 'Project Management',
                'slug'        => 'projects',
                'description' => 'Project planning, tasks, and timesheets.',
                'icon'        => 'briefcase',
                'is_core'     => false,
                'sort_order'  => 10,
            ],
            [
                'name'        => 'Asset Management',
                'slug'        => 'assets',
                'description' => 'Track fixed assets, maintenance, and depreciation.',
                'icon'        => 'hard-drive',
                'is_core'     => false,
                'sort_order'  => 11,
            ],
            [
                'name'        => 'Quality Assurance',
                'slug'        => 'quality',
                'description' => 'Manage inspections, non-conformance, and CAPA.',
                'icon'        => 'check-circle',
                'is_core'     => false,
                'sort_order'  => 12,
            ],
            [
                'name'        => 'IT Service Desk',
                'slug'        => 'it',
                'description' => 'Helpdesk ticketing and internal IT infrastructure.',
                'icon'        => 'monitor',
                'is_core'     => false,
                'sort_order'  => 13,
            ],
        ];

        foreach ($modules as $module) {
            Module::firstOrCreate(['slug' => $module['slug']], array_merge($module, ['is_active' => true]));
        }
    }
}
