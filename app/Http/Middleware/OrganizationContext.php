<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\UserOrganization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class OrganizationContext
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $orgId = $request->header('X-ORG-ID');

        if (!$orgId) {
            return response()->json([
                'success' => false,
                'message' => 'Organization context is required. Provide X-ORG-ID header.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $organization = Organization::where('uuid', $orgId)
            ->where('status', 'active')
            ->first();

        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found or inactive.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Verify the authenticated user belongs to this organization
        $user = $request->user();

        if ($user && !$user->is_super_admin && !$user->belongsToOrganization($organization->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You are not a member of this organization.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Bind to request and share globally across the app
        $request->merge(['current_organization' => $organization]);
        app()->instance('current_organization', $organization);

        // Also bind the current company
        $company = $organization->company;
        if ($company) {
            app()->instance('current_company', $company);
        }

        return $next($request);
    }
}
