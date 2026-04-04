<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\ModuleController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SidebarController;
use App\Http\Controllers\Api\V1\UserController;
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
|   permission:{slug}   → Checks         permission (applied per-route)
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

        // ─── Organization-Scoped Routes ───────────────────────────────────────
        // All routes below require X-ORG-ID header and active billing plan
        Route::middleware(['org.context', 'billing'])->group(function () {

            // Sidebar
            Route::get('sidebar', [SidebarController::class, 'index']);

            // Modules (Organization's available specific subscriptions)
            Route::get('modules', [ModuleController::class, 'index']);

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

            // Permissions — (Role assignments)
            Route::prefix('permissions')->group(function () {
                Route::get('/', [PermissionController::class, 'index'])
                    ->middleware('permission:admin.permission.view');
            });

            // Assign Permissions to Roles specifically
            Route::put('roles/{role}/permissions', [PermissionController::class, 'sync'])
                ->middleware('permission:admin.permission.assign');

            /*
            |-------------------------------------------------------------------
            | Additional Module Routes — add your ERP modules below.
            | Pattern:
            |   Route::apiResource('sales/invoices', InvoiceController::class)
            |        ->middleware('permission:sales.invoice.view');
            |-------------------------------------------------------------------
            */

        });
    });
});
