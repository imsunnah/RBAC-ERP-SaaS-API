<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\ModuleController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SidebarController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\SubmenuController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\HrmEmployeeController;
use App\Http\Controllers\Api\V1\HrmAttendanceController;
use App\Http\Controllers\Api\V1\AdmsPushController;
use App\Http\Controllers\Api\V1\BulkAccessController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\HrmLookupController;

use App\Http\Controllers\Api\V1\GlobalLookupController;
use App\Http\Controllers\Api\V1\WorkShiftController;
use App\Http\Controllers\Api\V1\HrmHolidayController;
use App\Http\Controllers\Api\V1\HrmLeaveApplicationController;
use App\Http\Controllers\Api\V1\HrmWeeklyMealController;
use App\Http\Controllers\Api\V1\HrmLeaveBalanceController;
use App\Http\Controllers\Api\V1\HrmEmployeeMealSelectionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — ERP SaaS v1
|--------------------------------------------------------------------------
|
| Middleware stack:
|   auth:api            → Laravel Passport token validation
|   org.context         → Resolves X-ORG-ID header, sets current_organization
|   billing             → Enforces active subscription plan
|   permission:{slug}   → Checks permission (applied per-route)
|
*/

// ─── Public Routes (no auth required) ──────────────────────────────────────────
Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
    });

    // ─── Authenticated Routes ─────────────────────────────────────────────────
    Route::middleware(['auth:api'])->group(function () {

        // Auth
        Route::prefix('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
        });

        // Global Management (no org context needed)
        Route::get('companies', [CompanyController::class, 'index']);
        Route::get('companies/{company}', [CompanyController::class, 'show']);
        Route::get('organizations', [OrganizationController::class, 'index']);
        Route::post('organizations', [OrganizationController::class, 'store']);
        Route::get('organizations/{organization}', [OrganizationController::class, 'show']);

        // ─── Organization-Scoped Routes ───────────────────────────────────────
        // All routes below require X-ORG-ID header and active billing plan
        Route::middleware(['org.context', 'billing'])->group(function () {

            // Sidebar
            Route::get('sidebar', [SidebarController::class, 'index']);


            // Modules — requires admin.module.* permissions
            Route::prefix('modules')->group(function () {
                Route::get('/', [ModuleController::class, 'index'])
                    ->middleware('permission:admin.module.view');
                Route::post('/', [ModuleController::class, 'store'])
                    ->middleware('permission:admin.module.create');
                Route::get('/{module}', [ModuleController::class, 'show'])
                    ->middleware('permission:admin.module.view');
                Route::put('/{module}', [ModuleController::class, 'update'])
                    ->middleware('permission:admin.module.update');
                Route::delete('/{module}', [ModuleController::class, 'destroy'])
                    ->middleware('permission:admin.module.delete');
            });

            // Roles — requires role management permissions
            Route::prefix('roles')->group(function () {
                Route::get('/', [RoleController::class, 'index'])
                    ->middleware('permission:admin.role.view');
                Route::post('/', [RoleController::class, 'store'])
                    ->middleware('permission:admin.role.create');
                Route::get('/{role}', [RoleController::class, 'show'])
                    ->middleware('permission:admin.role.view');
                Route::put('/{role}', [RoleController::class, 'update'])
                    ->middleware('permission:admin.role.update');
                Route::delete('/{role}', [RoleController::class, 'destroy'])
                    ->middleware('permission:admin.role.delete');
                Route::post('/{role}/users/bulk', [BulkAccessController::class, 'bulkAssignRole'])
                    ->middleware('permission:admin.role.assign');
            });

            // Unified Role Sync — sync modules, menus, submenus & permissions
            Route::put('sync-role/{role}', [RoleController::class, 'syncAll'])
                ->middleware('permission:admin.role.update');

            Route::get('syncRole/list', [RoleController::class, 'roleListData'])
                ->middleware('permission:admin.role.view');

            // Users — requires user management permissions
            Route::prefix('users')->group(function () {
                Route::get('/', [UserController::class, 'index'])
                    ->middleware('permission:admin.user.view');
                Route::post('/', [UserController::class, 'store'])
                    ->middleware('permission:admin.user.create');
                Route::get('/{user}', [UserController::class, 'show'])
                    ->middleware('permission:admin.user.view');
                Route::put('/{user}', [UserController::class, 'update'])
                    ->middleware('permission:admin.user.update');
                Route::delete('/{user}', [UserController::class, 'destroy'])
                    ->middleware('permission:admin.user.delete');
                Route::put('/{user}/roles', [UserController::class, 'syncRoles'])
                    ->middleware('permission:admin.user.update');
            });

            // Permissions — requires permission management permissions
            Route::prefix('permissions')->group(function () {
                Route::get('/grouped', [PermissionController::class, 'groupedByModule'])
                    ->middleware('permission:admin.permission.view');
                Route::get('/', [PermissionController::class, 'index'])
                    ->middleware('permission:admin.permission.view');
                Route::post('/', [PermissionController::class, 'store'])
                    ->middleware('permission:admin.permission.create');
                Route::get('/{permission}', [PermissionController::class, 'show'])
                    ->middleware('permission:admin.permission.view');
                Route::put('/{permission}', [PermissionController::class, 'update'])
                    ->middleware('permission:admin.permission.update');
                Route::delete('/{permission}', [PermissionController::class, 'destroy'])
                    ->middleware('permission:admin.permission.delete');
            });

            // Assign Permissions to Roles specifically
            Route::put('roles/{role}/permissions', [PermissionController::class, 'sync'])
                ->middleware('permission:admin.permission.assign');

            // Bulk Assign Direct User Permissions
            Route::post('permissions/bulk-assign', [BulkAccessController::class, 'bulkAssignPermissions'])
                ->middleware('permission:admin.permission.assign');

            // Menus — requires admin.menu.* permissions
            Route::prefix('menus')->group(function () {
                Route::get('/', [MenuController::class, 'index'])
                    ->middleware('permission:admin.menu.view');
                Route::post('/', [MenuController::class, 'store'])
                    ->middleware('permission:admin.menu.create');
                Route::get('/{menu}', [MenuController::class, 'show'])
                    ->middleware('permission:admin.menu.view');
                Route::put('/{menu}', [MenuController::class, 'update'])
                    ->middleware('permission:admin.menu.update');
                Route::delete('/{menu}', [MenuController::class, 'destroy'])
                    ->middleware('permission:admin.menu.delete');
            });

            // Submenus — requires admin.submenu.* permissions
            Route::prefix('submenus')->group(function () {
                Route::get('/', [SubmenuController::class, 'index'])
                    ->middleware('permission:admin.submenu.view');
                Route::post('/', [SubmenuController::class, 'store'])
                    ->middleware('permission:admin.submenu.create');
                Route::get('/{submenu}', [SubmenuController::class, 'show'])
                    ->middleware('permission:admin.submenu.view');
                Route::put('/{submenu}', [SubmenuController::class, 'update'])
                    ->middleware('permission:admin.submenu.update');
                Route::delete('/{submenu}', [SubmenuController::class, 'destroy'])
                    ->middleware('permission:admin.submenu.delete');
            });

            
            // HRM Employees — requires hrm.employee.* permissions
            Route::prefix('hrm-employees')->group(function () {
                Route::get('/dashboard', [HrmEmployeeController::class, 'dashboard'])
                    ->middleware('permission:hrm.employee.view');
                Route::get('/', [HrmEmployeeController::class, 'index'])
                    ->middleware('permission:hrm.employee.view');
                Route::post('/', [HrmEmployeeController::class, 'store'])
                    ->middleware('permission:hrm.employee.create');
                Route::get('/{hrmEmployee}', [HrmEmployeeController::class, 'show'])
                    ->middleware('permission:hrm.employee.view');
                Route::put('/{hrmEmployee}', [HrmEmployeeController::class, 'update'])
                    ->middleware('permission:hrm.employee.update');
                Route::delete('/{hrmEmployee}', [HrmEmployeeController::class, 'destroy'])
                    ->middleware('permission:hrm.employee.delete');
            });

            // HRM Attendance — requires hrm.attendance.view permission
            Route::prefix('attendance/hrm')->group(function () {

                Route::post('/dev-push-day-attendance', [HrmAttendanceController::class, 'devPushDayAttendance'])
                    ->middleware('permission:hrm.attendance.view');
                Route::get('/dashboard-summary', [HrmAttendanceController::class, 'employeeDashboardSummary'])
                    ->middleware('permission:hrm.attendance.view');
                Route::get('/employee-monthly-details', [HrmAttendanceController::class, 'employeeMonthlyDetails'])
                    ->middleware('permission:hrm.attendance.view');
                Route::get('/', [HrmAttendanceController::class, 'index'])
                    ->middleware('permission:hrm.attendance.view');
            });

            // ADMS Device Management (authenticated) — requires hrm.device.* permissions
            Route::prefix('adms')->group(function () {
                Route::get('devices', [AdmsPushController::class, 'devices'])
                    ->middleware('permission:hrm.device.view');
                Route::put('devices/{id}', [AdmsPushController::class, 'updateDevice'])
                    ->middleware('permission:hrm.device.update');
            });

            // Exports — queued CSV/XLSX exports
            Route::prefix('exports')->group(function () {
                Route::post('hrm-employees', [ExportController::class, 'exportEmployees'])
                    ->middleware('permission:hrm.employee.export');
                Route::post('hrm-attendance', [ExportController::class, 'exportAttendance'])
                    ->middleware('permission:hrm.attendance.export');
                Route::get('{filename}', [ExportController::class, 'download'])
                    ->where('filename', '.*');
            });

            // Global Lookups — unified metadata management
            Route::prefix('global-lookup-groups')->group(function () {
                Route::get('/', [GlobalLookupController::class, 'indexGroups'])
                    ->middleware('permission:admin.global-lookup.view');
                Route::post('/', [GlobalLookupController::class, 'storeGroup'])
                    ->middleware('permission:admin.global-lookup.create');
                Route::get('/{globalLookupGroup}', [GlobalLookupController::class, 'showGroup'])
                    ->middleware('permission:admin.global-lookup.view');
                Route::put('/{globalLookupGroup}', [GlobalLookupController::class, 'updateGroup'])
                    ->middleware('permission:admin.global-lookup.update');
                Route::delete('/{globalLookupGroup}', [GlobalLookupController::class, 'destroyGroup'])
                    ->middleware('permission:admin.global-lookup.delete');
            });

            Route::prefix('global-lookups')->group(function () {
                Route::get('/grouped', [GlobalLookupController::class, 'getGroupedLookups'])
                    ->middleware('permission:admin.global-lookup.view');
                Route::get('/', [GlobalLookupController::class, 'index'])
                    ->middleware('permission:admin.global-lookup.view');
                Route::post('/', [GlobalLookupController::class, 'store'])
                    ->middleware('permission:admin.global-lookup.create');
                Route::get('/{globalLookup}', [GlobalLookupController::class, 'show'])
                    ->middleware('permission:admin.global-lookup.view');
                Route::put('/{globalLookup}', [GlobalLookupController::class, 'update'])
                    ->middleware('permission:admin.global-lookup.update');
                Route::delete('/{globalLookup}', [GlobalLookupController::class, 'destroy'])
                    ->middleware('permission:admin.global-lookup.delete');
            });
            // HRM Lookups — unified metadata management
            Route::prefix('hrm-lookup-groups')->group(function () {
                Route::get('/', [HrmLookupController::class, 'indexGroups'])
                    ->middleware('permission:admin.hrm-lookup.view');
                Route::post('/', [HrmLookupController::class, 'storeGroup'])
                    ->middleware('permission:admin.hrm-lookup.create');
                Route::get('/{hrmLookupGroup}', [HrmLookupController::class, 'showGroup'])
                    ->middleware('permission:admin.hrm-lookup.view');
                Route::put('/{hrmLookupGroup}', [HrmLookupController::class, 'updateGroup'])
                    ->middleware('permission:admin.hrm-lookup.update');
                Route::delete('/{hrmLookupGroup}', [HrmLookupController::class, 'destroyGroup'])
                    ->middleware('permission:admin.hrm-lookup.delete');
            });

            Route::prefix('hrm-lookups')->group(function () {
                Route::get('/grouped', [HrmLookupController::class, 'getGroupedLookups'])
                    ->middleware('permission:admin.hrm-lookup.view');
                Route::get('/', [HrmLookupController::class, 'index'])
                    ->middleware('permission:admin.hrm-lookup.view');
                Route::post('/', [HrmLookupController::class, 'store'])
                    ->middleware('permission:admin.hrm-lookup.create');
                Route::get('/{hrmLookup}', [HrmLookupController::class, 'show'])
                    ->middleware('permission:admin.hrm-lookup.view');
                Route::put('/{hrmLookup}', [HrmLookupController::class, 'update'])
                    ->middleware('permission:admin.hrm-lookup.update');
                Route::delete('/{hrmLookup}', [HrmLookupController::class, 'destroy'])
                    ->middleware('permission:admin.hrm-lookup.delete');
            });

            // Work Shifts
            Route::prefix('work-shifts')->group(function () {
                Route::get('/', [WorkShiftController::class, 'index'])
                    ->middleware('permission:hrm.shift.view');
                Route::post('/', [WorkShiftController::class, 'store'])
                    ->middleware('permission:hrm.shift.create');
                Route::get('/{workShift}', [WorkShiftController::class, 'show'])
                    ->middleware('permission:hrm.shift.view');
                Route::put('/{workShift}', [WorkShiftController::class, 'update'])
                    ->middleware('permission:hrm.shift.update');
                Route::delete('/{workShift}', [WorkShiftController::class, 'destroy'])
                    ->middleware('permission:hrm.shift.delete');
            });

            // Holidays
            Route::prefix('hrm-holidays')->group(function () {
                Route::get('/', [HrmHolidayController::class, 'index'])
                    ->middleware('permission:hrm.holiday.view');
                Route::get('/calendar', [HrmHolidayController::class, 'calendar'])
                    ->middleware('permission:hrm.holiday.view');
                Route::post('/by-date', [HrmHolidayController::class, 'updateByDate'])
                    ->middleware('permission:hrm.holiday.update');
                Route::post('/', [HrmHolidayController::class, 'store'])
                    ->middleware('permission:hrm.holiday.create');
                Route::get('/{hrmHoliday}', [HrmHolidayController::class, 'show'])
                    ->middleware('permission:hrm.holiday.view');
                Route::put('/{hrmHoliday}', [HrmHolidayController::class, 'update'])
                    ->middleware('permission:hrm.holiday.update');
                Route::delete('/{hrmHoliday}', [HrmHolidayController::class, 'destroy'])
                    ->middleware('permission:hrm.holiday.delete');
            });

            // Leave Applications
            Route::prefix('leave-applications')->group(function () {
                Route::get('/', [HrmLeaveApplicationController::class, 'index'])
                    ->middleware('permission:hrm.leave.view');
                Route::post('/', [HrmLeaveApplicationController::class, 'store'])
                    ->middleware('permission:hrm.leave.create');
                Route::get('/{hrmLeaveApplication}', [HrmLeaveApplicationController::class, 'show'])
                    ->middleware('permission:hrm.leave.view');
                Route::post('/{hrmLeaveApplication}', [HrmLeaveApplicationController::class, 'update'])
                    ->middleware('permission:hrm.leave.update');
                Route::delete('/{hrmLeaveApplication}', [HrmLeaveApplicationController::class, 'destroy'])
                    ->middleware('permission:hrm.leave.delete');
                Route::patch('/{hrmLeaveApplication}/status', [HrmLeaveApplicationController::class, 'updateStatus'])
                    ->middleware('permission:hrm.leave.approve');
            });

            // Leave Balances
            Route::prefix('leave-balances')->group(function () {
                Route::get('/', [HrmLeaveBalanceController::class, 'index'])
                    ->middleware('permission:hrm.leave.view');
                Route::post('/', [HrmLeaveBalanceController::class, 'store'])
                    ->middleware('permission:hrm.leave.create');
            });

            // Weekly Meals Setup
            Route::prefix('hrm-weekly-meals')->group(function () {
                Route::get('/', [HrmWeeklyMealController::class, 'index'])
                    ->middleware('permission:hrm.meal.view');
                Route::post('/', [HrmWeeklyMealController::class, 'store'])
                    ->middleware('permission:hrm.meal.create');
                Route::get('/{hrmWeeklyMeal}', [HrmWeeklyMealController::class, 'show'])
                    ->middleware('permission:hrm.meal.view');
                Route::put('/{hrmWeeklyMeal}', [HrmWeeklyMealController::class, 'update'])
                    ->middleware('permission:hrm.meal.update');
                Route::delete('/{hrmWeeklyMeal}', [HrmWeeklyMealController::class, 'destroy'])
                    ->middleware('permission:hrm.meal.delete');
            });

            // Employee Meal Selections
            Route::prefix('hrm-employee-meal-selections')->group(function () {
                Route::get('/dashboard', [HrmEmployeeMealSelectionController::class, 'dashboard'])
                    ->middleware('permission:hrm.meal.view');
                Route::get('/user-meal-overview', [HrmEmployeeMealSelectionController::class, 'mealOverview'])
                    ->middleware('permission:hrm.meal.view');    
                Route::get('/today/meal-lists', [HrmEmployeeMealSelectionController::class, 'todayMealList'])
                    ->middleware('permission:hrm.meal.view');
                Route::get('/', [HrmEmployeeMealSelectionController::class, 'index'])
                    ->middleware('permission:hrm.meal.view');
                Route::post('/', [HrmEmployeeMealSelectionController::class, 'store'])
                    ->middleware('permission:hrm.meal.create');
            });
        });
    });
});
