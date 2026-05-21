<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Log;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EnforceBillingPlan
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $organization = app('current_organization');
        // Ensure the organization’s plan relationship is loaded
        if ($organization) {
            $organization->load('plan');
        }

        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization context missing.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $plan = $organization->plan;

        // Allow read-only access (GET, HEAD, OPTIONS) even if the plan is expired
        if ($request->isMethod('GET') || $request->isMethod('HEAD') || $request->isMethod('OPTIONS')) {
            return $next($request);
        }

        // Log detailed plan information for debugging
        Log::info('EnforceBillingPlan middleware check', [
            'organization_id' => $organization->id ?? null,
            'plan_status' => optional($organization->plan)->status,
            'plan_expires_at' => optional($organization->plan)->expires_at,
            'plan_is_active' => optional($organization->plan)->isActive(),
        ]);
        // If there is no plan or the plan is not active, block the request
        if (!$plan || !$plan->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Your subscription plan has expired or is inactive. Please renew to continue.',
                'code' => 'PLAN_EXPIRED',
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        return $next($request);
    }
}
