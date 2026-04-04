<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EnforceBillingPlan
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $organization = app('current_organization');

        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization context missing.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $plan = $organization->plan;

        if (!$plan || $plan->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Your subscription plan has expired or is inactive. Please renew to continue.',
                'code' => 'PLAN_EXPIRED',
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        return $next($request);
    }
}
