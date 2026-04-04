<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LogoutRequest;
use App\Http\Requests\Auth\MeRequest;
use App\Http\Resources\Auth\UserResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService)
    {
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
                'user' => new UserResource($user->load('organizations')),
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
     */
    public function me(MeRequest $request): JsonResponse
    {
        $user = $request->user()->load(['organizations:id,uuid,name,slug,logo']);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ]);
    }
}
