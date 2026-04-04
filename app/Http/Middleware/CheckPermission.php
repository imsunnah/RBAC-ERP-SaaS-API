<?php

namespace App\Http\Middleware;

use App\Services\RBAC\RBACService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CheckPermission
{
    public function __construct(private RBACService $rbacService)
    {
    }

    /**
     * Usage in route: ->middleware('permission:sales.invoice.create')
     */
    public function handle(Request $request, Closure $next, string $permission): SymfonyResponse
    {
        $user = $request->user();
        $organization = app('current_organization');

        if (!$user || !$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated or missing organization context.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->rbacService->hasPermission($user, $organization->id, $permission)) {
            return response()->json([
                'success' => false,
                'message' => "Forbidden. Missing permission: [{$permission}]",
                'code' => 'PERMISSION_DENIED',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
