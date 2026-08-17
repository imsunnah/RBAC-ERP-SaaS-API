<?php

namespace App\Providers;

use App\Repositories\Base\BaseRepository;
use App\Repositories\Contracts\RepositoryInterface;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditService;
use App\Services\Auth\AuthService;
use App\Services\Billing\BillingService;
use App\Services\RBAC\RBACService;
use App\Services\RBAC\RoutePermissionSyncer;
use App\Services\Sidebar\SidebarService;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ── Repository bindings ───────────────────────────────────────────────
        $this->app->bind(RepositoryInterface::class, BaseRepository::class);
        $this->app->bind(UserRepository::class, UserRepository::class);
        $this->app->bind(RoleRepository::class, RoleRepository::class);

        // ── Service bindings (singletons — built once per request lifecycle) ──
        $this->app->singleton(RBACService::class);
        $this->app->singleton(SidebarService::class);
        $this->app->singleton(AuditService::class);
        $this->app->singleton(AuthService::class);
        $this->app->singleton(BillingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ── Passport token expiry ─────────────────────────────────────────────
        Passport::tokensExpireIn(new \DateInterval('PT6H'));
        Passport::refreshTokensExpireIn(new \DateInterval('P30D'));
        Passport::personalAccessTokensExpireIn(new \DateInterval('PT6H'));

        // ── Log slow queries in debug mode ────────────────────────────────────
        if (config('app.debug')) {
            DB::listen(function ($query) {
                if ($query->time > 1000) { // >1 second
                    Log::warning('Slow query detected', [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'time_ms' => $query->time,
                    ]);
                }
            });
        }
        Scramble::afterOpenApiGenerated(function (OpenApi $openApi) {
            $openApi->components->addSecurityScheme('bearer', SecurityScheme::http('bearer'));
            $openApi->components->addSecurityScheme('X-ORG-ID', SecurityScheme::apiKey('header', 'X-ORG-ID'));

            $openApi->security = [
                new SecurityRequirement([
                    'bearer' => [],
                    'X-ORG-ID' => [],
                ]),
            ];
        });

        // ── Auto-sync route permissions ───────────────────────────────────────
        $this->app->booted(function () {
            RoutePermissionSyncer::sync();
        });
    }
}
