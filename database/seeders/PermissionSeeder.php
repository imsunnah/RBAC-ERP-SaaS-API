<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Permission naming: {module}.{resource}.{action}
     */
    private array $permissions = [
        // ── Administration ────────────────────────────────────────────────────
        'admin' => [
            ['name' => 'View Roles', 'slug' => 'admin.role.view', 'group' => 'admin.role'],
            ['name' => 'Create Role', 'slug' => 'admin.role.create', 'group' => 'admin.role'],
            ['name' => 'Update Role', 'slug' => 'admin.role.update', 'group' => 'admin.role'],
            ['name' => 'Delete Role', 'slug' => 'admin.role.delete', 'group' => 'admin.role'],
            ['name' => 'View Users', 'slug' => 'admin.user.view', 'group' => 'admin.user'],
            ['name' => 'Create User', 'slug' => 'admin.user.create', 'group' => 'admin.user'],
            ['name' => 'Update User', 'slug' => 'admin.user.update', 'group' => 'admin.user'],
            ['name' => 'Delete User', 'slug' => 'admin.user.delete', 'group' => 'admin.user'],
            ['name' => 'View Permissions', 'slug' => 'admin.permission.view', 'group' => 'admin.permission'],
            ['name' => 'Assign Permission', 'slug' => 'admin.permission.assign', 'group' => 'admin.permission'],
            ['name' => 'View Modules', 'slug' => 'admin.module.view', 'group' => 'admin.module'],
            ['name' => 'Manage Modules', 'slug' => 'admin.module.manage', 'group' => 'admin.module'],
        ],

        // ── HRM ───────────────────────────────────────────────────────────────
        'hrm' => [
            ['name' => 'View Employees', 'slug' => 'hrm.employee.view', 'group' => 'hrm.employee'],
            ['name' => 'Create Employee', 'slug' => 'hrm.employee.create', 'group' => 'hrm.employee'],
            ['name' => 'Update Employee', 'slug' => 'hrm.employee.update', 'group' => 'hrm.employee'],
            ['name' => 'Delete Employee', 'slug' => 'hrm.employee.delete', 'group' => 'hrm.employee'],
            ['name' => 'View Attendance', 'slug' => 'hrm.attendance.view', 'group' => 'hrm.attendance'],
            ['name' => 'Manage Attendance', 'slug' => 'hrm.attendance.manage', 'group' => 'hrm.attendance'],
            ['name' => 'View Leave', 'slug' => 'hrm.leave.view', 'group' => 'hrm.leave'],
            ['name' => 'Approve Leave', 'slug' => 'hrm.leave.approve', 'group' => 'hrm.leave'],
            ['name' => 'View Payroll', 'slug' => 'hrm.payroll.view', 'group' => 'hrm.payroll'],
            ['name' => 'Process Payroll', 'slug' => 'hrm.payroll.process', 'group' => 'hrm.payroll'],
        ],

        // ── Sales ─────────────────────────────────────────────────────────────
        'sales' => [
            ['name' => 'View Customers', 'slug' => 'sales.customer.view', 'group' => 'sales.customer'],
            ['name' => 'Create Customer', 'slug' => 'sales.customer.create', 'group' => 'sales.customer'],
            ['name' => 'Update Customer', 'slug' => 'sales.customer.update', 'group' => 'sales.customer'],
            ['name' => 'View Invoices', 'slug' => 'sales.invoice.view', 'group' => 'sales.invoice'],
            ['name' => 'Create Invoice', 'slug' => 'sales.invoice.create', 'group' => 'sales.invoice'],
            ['name' => 'Update Invoice', 'slug' => 'sales.invoice.update', 'group' => 'sales.invoice'],
            ['name' => 'Delete Invoice', 'slug' => 'sales.invoice.delete', 'group' => 'sales.invoice'],
            ['name' => 'View Orders', 'slug' => 'sales.order.view', 'group' => 'sales.order'],
            ['name' => 'Create Order', 'slug' => 'sales.order.create', 'group' => 'sales.order'],
            ['name' => 'Approve Order', 'slug' => 'sales.order.approve', 'group' => 'sales.order'],
        ],

        // ── Inventory ─────────────────────────────────────────────────────────
        'inventory' => [
            ['name' => 'View Products', 'slug' => 'inventory.product.view', 'group' => 'inventory.product'],
            ['name' => 'Create Product', 'slug' => 'inventory.product.create', 'group' => 'inventory.product'],
            ['name' => 'Update Product', 'slug' => 'inventory.product.update', 'group' => 'inventory.product'],
            ['name' => 'Delete Product', 'slug' => 'inventory.product.delete', 'group' => 'inventory.product'],
            ['name' => 'View Stock', 'slug' => 'inventory.stock.view', 'group' => 'inventory.stock'],
            ['name' => 'Adjust Stock', 'slug' => 'inventory.stock.adjust', 'group' => 'inventory.stock'],
            ['name' => 'View Warehouses', 'slug' => 'inventory.warehouse.view', 'group' => 'inventory.warehouse'],
            ['name' => 'Manage Warehouse', 'slug' => 'inventory.warehouse.manage', 'group' => 'inventory.warehouse'],
        ],

        // ── Finance ───────────────────────────────────────────────────────────
        'finance' => [
            ['name' => 'View Accounts', 'slug' => 'finance.account.view', 'group' => 'finance.account'],
            ['name' => 'Create Account', 'slug' => 'finance.account.create', 'group' => 'finance.account'],
            ['name' => 'View Ledger', 'slug' => 'finance.ledger.view', 'group' => 'finance.ledger'],
            ['name' => 'Post Voucher', 'slug' => 'finance.voucher.post', 'group' => 'finance.voucher'],
            ['name' => 'View Reports', 'slug' => 'finance.report.view', 'group' => 'finance.report'],
            ['name' => 'Export Reports', 'slug' => 'finance.report.export', 'group' => 'finance.report'],
        ],
    ];

    public function run(): void
    {
        $org = Organization::where('slug', 'demo-corp')->firstOrFail();

        // Load roles
        $adminRole = Role::where('organization_id', $org->id)->where('slug', 'org-admin')->firstOrFail();
        $managerRole = Role::where('organization_id', $org->id)->where('slug', 'manager')->firstOrFail();
        $employeeRole = Role::where('organization_id', $org->id)->where('slug', 'employee')->firstOrFail();

        foreach ($this->permissions as $moduleSlug => $perms) {
            $module = Module::where('slug', $moduleSlug)->first();

            if (!$module) {
                continue;
            }

            foreach ($perms as $permData) {
                $permission = Permission::firstOrCreate(
                    ['slug' => $permData['slug']],
                    [
                        'module_id' => $module->id,
                        'name' => $permData['name'],
                        'description' => $permData['name'] . ' in ' . $module->name,
                        'group' => $permData['group'],
                    ]
                );

                // ── Assign permissions to roles ───────────────────────────────
                // Admin gets ALL permissions
                $adminRole->permissions()->syncWithoutDetaching([
                    $permission->id => ['is_granted' => true],
                ]);

                // Manager gets view + limited actions (not delete)
                if (!str_contains($permData['slug'], '.delete')) {
                    $managerRole->permissions()->syncWithoutDetaching([
                        $permission->id => ['is_granted' => true],
                    ]);
                }

                // Employee gets only view permissions
                if (str_ends_with($permData['slug'], '.view')) {
                    $employeeRole->permissions()->syncWithoutDetaching([
                        $permission->id => ['is_granted' => true],
                    ]);
                }
            }
        }

        $this->command->info('Permissions seeded and assigned to roles.');
    }
}
