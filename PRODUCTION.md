# Production Optimization Notes for Laravel 12 API-only ERP SaaS

This document provides a set of best practices and optimizations to ensure that the produced Multi-Organization, Module-Based ERP SaaS API scales effectively and runs efficiently in a production environment.

## 1. Caching Strategy
Permissions and sidebars are already cached by the `RBACService` and `SidebarService` per `organization_id` and `user_id`. Ensure you are using `Redis` (`CACHE_STORE=redis`) in production.
- Use `php artisan cache:clear` and related clearing commands during deployments.
- Avoid using the database driver for `CACHE` or `SESSION` (we updated `.env` to prefer `redis` or `file`).
- Ensure memory limits for Redis are appropriate, configuring an `allkeys-lru` eviction policy.

## 2. Queue Workers for Auditing and Heavy Tasks
Currently, the `AuditService` logs data synchronously. In high-traffic scenarios, database write contention around `AuditLog` can bottleneck requests.
- **Optimization:** Offload `AuditService::log()` to a Queue Job.
- Implement Laravel Horizon for managing Redis queues.
- Process heavy imports/exports, reports, and emails via queues.
- Supervisor config: run multiple workers (`numprocs=8`) for high concurrency.

## 3. Database Optimizations (MySQL)
The database uses UUIDs across all tables. This can hit performance constraints due to index fragmentation compared to auto-incrementing integers.
- **Optimization:** If moving to massive scale (millions of rows per table), consider shifting to ordered UUIDs (UUIDv7, supported natively in Laravel).
- Add specific composite indexes where frequent queries hit. For example, `user_permissions` and `role_permissions` might heavily leverage the `organization_id` + `slug` combinations. The migrations currently cover standard relations, but run `EXPLAIN` statements on slow query logs.
- Keep `DB::listen` in `AppServiceProvider` strictly disabled outside of debug mode to save memory.

## 4. Laravel Route and Config Optimization
Before deploying to production, run the standard performance boosts:
```bash
php artisan config:cache
php artisan route:cache
php artisan optimize
```

## 5. Avoiding N+1 Queries
Eloquent lazy-loads relationships by default. In the ERP, complex relation trees (like User -> Roles -> Permissions) can easily trigger N+1 queries.
- **Optimization:** In `AppServiceProvider` inside `boot()`, prevent lazy loading entirely in development to spot missing eager loads early:
```php
Model::preventLazyLoading(! app()->isProduction());
```
- We used `.load()` and `.with()` heavily in `RBACService`, `SidebarService`, and Repositories to adhere to this. 

## 6. Passport Personal Access Tokens (PAT) Scalability
Since this is an API-only SaaS using `Laravel Passport`, tokens can pile up in the database.
- **Optimization:** Regularly run a scheduled job to prune revoked or expired tokens:
```php
// In Console/Kernel.php or App\Console\Commands
protected function schedule(Schedule $schedule)
{
    $schedule->command('passport:purge')->daily();
}
```

## 7. PHP Opcache & FPM Tuning
Ensure PHP 8.2+ settings are dialed in for the stateless API:
- Enable `opcache.enable=1` and `opcache.validate_timestamps=0` (in production).
- Enable `opcache.preload` to load Laravel core files into memory upfront.
- Tune `php-fpm` pool settings (`pm = dynamic`, scale `pm.max_children` based on server RAM).

## 8. Security Optimizations
- **Rate Limiting:** Enforce API rate limits per user/organization in `RouteServiceProvider` to prevent brute force or denial-of-service (e.g., `RateLimiter::for('api', function (Request $request) { return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()); });`).
- **CORS:** Restrict `config/cors.php` `allowed_origins` to strictly your frontend domains.
- Ensure the `EnforceBillingPlan` runs before heavy database queries to block unpaid actors efficiently.

## 9. Horizontal Scalability Check
The codebase is structured using the Service-Repository pattern without tight coupling. State is stored remotely via Redis (Session / Cache / Queue). Because of this, you can seamlessly scale horizontally:
- Place instances behind an AWS ALB or NGINX Load Balancer.
- Route database connections through an RDS Proxy/Connection pooler.
