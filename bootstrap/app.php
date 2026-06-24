<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnforceBillingPlan;
use App\Http\Middleware\OrganizationContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // ── Ensure all API responses return JSON ──────────────────────────────
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // ── Named middleware aliases ──────────────────────────────────────────
        $middleware->alias([
            'org.context' => OrganizationContext::class,
            'billing' => EnforceBillingPlan::class,
            'permission' => CheckPermission::class,
        ]);

        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'iclock/*',
            'cdata',
            'getrequest',
            'i/*'
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Return all unhandled exceptions as JSON for API consumers
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage(),
                        'errors' => $e->errors(),
                    ], $e->status);
                }

                $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                $message = $e->getMessage() ?: 'An unexpected error occurred.';

                if ($status === 500 && !config('app.debug')) {
                    $message = 'Internal server error.';
                }

                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], $status);
            }
        });
    })->create();
