<?php

namespace App\Http\Middleware;

use App\Services\RBAC\RBACService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CheckPermission
{
    public function __construct(private RBACService $rbacService) {}

    /**
     * Usage in route: ->middleware('permission:sales.invoice.create')
     */
    public function handle(Request $request, Closure $next, string $permission): SymfonyResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Super admins bypass all permission checks and do not require organization context
        if ($user->is_super_admin) {
            return $next($request);
        }

        // Resolve current organization context if available
        $organization = app()->bound('current_organization') ? app('current_organization') : null;

        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization context is required. Provide valid X-ORG-ID header.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $hasPerm = $this->rbacService->hasPermission($user, $organization->id, $permission);

        if (!$hasPerm) {
            return response()->json([
                'success' => false,
                'message' => "Forbidden. Missing permission: [{$permission}]",
                'code' => 'PERMISSION_DENIED',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
