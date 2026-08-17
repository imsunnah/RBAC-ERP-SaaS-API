<?php

namespace App\Services\RBAC;

use App\Models\Permission;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RoutePermissionSyncer
{
    /**
     * Synchronize permission slugs from routes into the database and optimize.
     */
    public static function sync(bool $force = false): void
    {
        // 1. Only run in local development environment unless forced
        if (!$force && !app()->environment('local')) {
            return;
        }

        // 2. Ensure DB tables exist to prevent errors during migrations
        if (!Schema::hasTable('modules') || !Schema::hasTable('permissions')) {
            return;
        }

        // 3. Handle stale route/config cache
        try {
            $cachedRoutesPath = app()->getCachedRoutesPath();
            $apiRoutesPath = base_path('routes/api.php');
            $webRoutesPath = base_path('routes/web.php');

            $cacheMtime = file_exists($cachedRoutesPath) ? filemtime($cachedRoutesPath) : 0;
            $routesMtime = max(
                file_exists($apiRoutesPath) ? filemtime($apiRoutesPath) : 0,
                file_exists($webRoutesPath) ? filemtime($webRoutesPath) : 0
            );

            // If routes file was updated after caching, clear the cache files
            if ($cacheMtime > 0 && $routesMtime > $cacheMtime) {
                @unlink($cachedRoutesPath);
                
                $cachedConfigPath = app()->getCachedConfigPath();
                if (file_exists($cachedConfigPath)) {
                    @unlink($cachedConfigPath);
                }

                $cachedServicesPath = app()->getCachedServicesPath();
                if (file_exists($cachedServicesPath)) {
                    @unlink($cachedServicesPath);
                }

                $cachedPackagesPath = app()->getCachedPackagesPath();
                if (file_exists($cachedPackagesPath)) {
                    @unlink($cachedPackagesPath);
                }

                // If not running in console, trigger optimization to rebuild caches
                if (!app()->runningInConsole()) {
                    Artisan::call('optimize');
                    return;
                }
            }
        } catch (\Throwable $e) {
            logger()->error('RoutePermissionSyncer cache clear failed: ' . $e->getMessage());
        }

        // 4. Extract all permission middleware values from routes
        $routes = Route::getRoutes();
        $routePermissions = [];

        foreach ($routes as $route) {
            $middlewares = $route->gatherMiddleware();
            foreach ($middlewares as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'permission:')) {
                    $permissionSlug = substr($middleware, 11);
                    if ($permissionSlug) {
                        $routePermissions[] = $permissionSlug;
                    }
                }
            }
        }

        $routePermissions = array_unique($routePermissions);
        sort($routePermissions);

        if (empty($routePermissions)) {
            return;
        }

        // 5. Use cache to quickly check if route permission list is identical to last check
        $hash = md5(implode(',', $routePermissions));
        $cachedHash = Cache::get('synced_route_permissions_hash');

        if ($hash === $cachedHash) {
            return;
        }

        // 6. Sync any missing permissions to database
        $newPermissionCreated = false;

        foreach ($routePermissions as $slug) {
            $exists = Permission::where('slug', $slug)->exists();
            if (!$exists) {
                // Resolve/create module based on the first part of the slug (e.g. "hrm.employee.view" -> "hrm")
                $parts = explode('.', $slug);
                $moduleSlug = $parts[0] ?? 'general';

                $module = DB::table('modules')->where('slug', $moduleSlug)->first();
                if (!$module) {
                    $moduleId = DB::table('modules')->insertGetId([
                        'uuid' => (string) Str::uuid(),
                        'name' => ucwords($moduleSlug),
                        'slug' => $moduleSlug,
                        'icon' => 'settings',
                        'is_active' => true,
                        'sort_order' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $moduleId = $module->id;
                }

                // Auto-generate name and group
                // Group is everything except the last part (e.g. "hrm.employee")
                // Action is the last part (e.g. "view") -> "View Employee"
                $group = count($parts) > 1 ? implode('.', array_slice($parts, 0, -1)) : $moduleSlug;
                $action = end($parts);
                $entityParts = array_slice($parts, 1, -1);
                
                $nameParts = array_merge([$action], $entityParts);
                $name = ucwords(str_replace('_', ' ', implode(' ', $nameParts)));

                Permission::create([
                    'module_id' => $moduleId,
                    'name' => $name,
                    'slug' => $slug,
                    'group' => $group,
                    'description' => 'Auto-generated permission from route definitions',
                ]);

                $newPermissionCreated = true;
            }
        }

        // 7. Update cache to prevent repeating on next requests
        Cache::forever('synced_route_permissions_hash', $hash);

        // 8. Re-run php artisan optimize if a new permission was created and not in console
        if ($newPermissionCreated && !app()->runningInConsole()) {
            try {
                Artisan::call('optimize');
            } catch (\Throwable $e) {
                logger()->error('Failed to auto-run php artisan optimize: ' . $e->getMessage());
            }
        }
    }
}
