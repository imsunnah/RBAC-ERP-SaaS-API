<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LogoutRequest;
use App\Http\Requests\Auth\MeRequest;
use App\Http\Resources\Auth\UserResource;
use App\Services\Auth\AuthService;
use App\Services\RBAC\RBACService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private RBACService $rbacService,
    ) {
    }

    /**
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            $this->authService->logLoginAttempt($request->email, 'failed', 'Invalid credentials');

            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->status !== 'active') {
            Auth::logout();
            $this->authService->logLoginAttempt($request->email, 'blocked', 'Account suspended', $user);

            return response()->json([
                'success' => false,
                'message' => 'Your account has been suspended.',
            ], 403);
        }

        // Check if the user has at least one active organization plan
        if (!$user->is_super_admin) {
            // Ensure organizations and their plans are eager-loaded
            $user->load('organizations.plan');
            // Log detailed organization plan information for debugging
            $orgPlans = $user->organizations->map(function ($org) {
                return [
                    'org_id' => $org->id,
                    'plan_status' => optional($org->plan)->status,
                    'plan_expires_at' => optional($org->plan)->expires_at,
                    'plan_is_active' => optional($org->plan)->isActive(),
                ];
            });
            Log::info('Auth login plan check', ['user_id' => $user->id, 'org_plans' => $orgPlans]);

            $hasActivePlan = false;

            foreach ($user->organizations as $org) {
                if ($org->plan && $org->plan->isActive()) {
                    $hasActivePlan = true;
                    break;
                }
            }

            if (!$hasActivePlan && $user->organizations->count() > 0) {
                Auth::logout();
                $this->authService->logLoginAttempt($request->email, 'blocked', 'Subscription expired', $user);

                return response()->json([
                    'success' => false,
                    'message' => 'Your subscription plan has expired or is inactive. Please renew to continue.',
                    'code' => 'PLAN_EXPIRED',
                ], 402);
            }
        }

        $token = $user->createToken('ERP Personal Access Token')->accessToken;

        $this->authService->logLoginAttempt($request->email, 'success', null, $user);
        $this->authService->updateLastLogin($user);
        $this->authService->recordPasswordHistory($user, $user->password);

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $token,
                // 'user' => new UserResource($user->load(['organizations.company'])),
            ],
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(LogoutRequest $request): JsonResponse
    {
        $request->user()->token()->revoke();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * GET /api/v1/auth/me
     * Returns authenticated user profile with resolved permissions for current org.
     */
    public function me(MeRequest $request): JsonResponse
    {
        $user = $request->user()->load(['organizations.company', 'organizations.modules', 'organizations.modules.menus' => function($query) {
            $query->orderBy('sort_order', 'asc');
        }, 'organizations.modules.menus.submenus']);

        $data = (new UserResource($user))->toArray($request);

        // If org context is available, include resolved permissions and allowed modules
        if (app()->bound('current_organization')) {
            $org = app('current_organization');
            $data['permissions'] = $this->rbacService->getUserPermissions($user, $org->id);

            $slugToFrontendIds = [
                'hrm'         => ['hr', 'payroll'],
                'sales'       => ['sales'],
                'inventory'   => ['inventory'],
                'finance'     => ['finance'],
                'procurement' => ['procurement'],
                'projects'    => ['projects'],
                'meetings'    => ['meetings'],
                'mrp'         => ['mrp'],
                'assets'      => ['assets'],
                'quality'     => ['quality'],
                'it'          => ['it'],
                'admin'       => ['admin'],
                'payroll'     => ['payroll'],
                'crm'         => ['sales'],  // legacy crm maps to sales module
            ];

            if ($user->is_super_admin) {
                // Superadmin sees ALL frontend modules
                $data['allowed_modules'] = [
                    'hr', 'payroll', 'sales', 'inventory', 'finance',
                    'procurement', 'mrp', 'meetings', 'projects',
                    'assets', 'quality', 'it', 'admin',
                ];
            } else {
                $roles = $user->rolesInOrg($org->id)->load('modules');
                $allowedModuleIds = [];
                foreach ($roles as $role) {
                    foreach ($role->modules as $module) {
                        if ($module->is_active) {
                            $dbSlug = $module->slug;
                            $frontendIds = $slugToFrontendIds[$dbSlug] ?? [$dbSlug];
                            foreach ($frontendIds as $fId) {
                                $allowedModuleIds[] = $fId;
                            }
                        }
                    }
                }
                $data['allowed_modules'] = array_values(array_unique($allowedModuleIds));
            }
        } else {
            $data['allowed_modules'] = [];
        }


        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
