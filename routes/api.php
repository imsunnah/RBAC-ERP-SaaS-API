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

use App\Http\Controllers\Api\V1\GenderController;
use App\Http\Controllers\Api\V1\ReligionController;
use App\Http\Controllers\Api\V1\MaritalStatusController;
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
                Route::put('/{role}/modules', [RoleController::class, 'syncModules'])
                    ->middleware('permission:admin.role.update');
                Route::put('/{role}/menus', [RoleController::class, 'syncMenus'])
                    ->middleware('permission:admin.role.update');
                Route::post('/{role}/users/bulk', [BulkAccessController::class, 'bulkAssignRole'])
                    ->middleware('permission:admin.role.assign');
            });

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

            // ─── Metadata / Setup Routes ─────────────────────────────────────

            // Genders — global lookup
            Route::prefix('genders')->group(function () {
                Route::get('/', [GenderController::class, 'index'])
                    ->middleware('permission:admin.gender.view');
                Route::post('/', [GenderController::class, 'store'])
                    ->middleware('permission:admin.gender.create');
                Route::get('/{gender}', [GenderController::class, 'show'])
                    ->middleware('permission:admin.gender.view');
                Route::put('/{gender}', [GenderController::class, 'update'])
                    ->middleware('permission:admin.gender.update');
                Route::delete('/{gender}', [GenderController::class, 'destroy'])
                    ->middleware('permission:admin.gender.delete');
            });

            // Religions — global lookup
            Route::prefix('religions')->group(function () {
                Route::get('/', [ReligionController::class, 'index'])
                    ->middleware('permission:admin.religion.view');
                Route::post('/', [ReligionController::class, 'store'])
                    ->middleware('permission:admin.religion.create');
                Route::get('/{religion}', [ReligionController::class, 'show'])
                    ->middleware('permission:admin.religion.view');
                Route::put('/{religion}', [ReligionController::class, 'update'])
                    ->middleware('permission:admin.religion.update');
                Route::delete('/{religion}', [ReligionController::class, 'destroy'])
                    ->middleware('permission:admin.religion.delete');
            });

            // Marital Statuses — global lookup
            Route::prefix('marital-statuses')->group(function () {
                Route::get('/', [MaritalStatusController::class, 'index'])
                    ->middleware('permission:admin.marital-status.view');
                Route::post('/', [MaritalStatusController::class, 'store'])
                    ->middleware('permission:admin.marital-status.create');
                Route::get('/{maritalStatus}', [MaritalStatusController::class, 'show'])
                    ->middleware('permission:admin.marital-status.view');
                Route::put('/{maritalStatus}', [MaritalStatusController::class, 'update'])
                    ->middleware('permission:admin.marital-status.update');
                Route::delete('/{maritalStatus}', [MaritalStatusController::class, 'destroy'])
                    ->middleware('permission:admin.marital-status.delete');
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
        });
    });
});
