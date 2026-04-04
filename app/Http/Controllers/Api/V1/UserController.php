<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\IndexUserRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\SyncUserRolesRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\User\UserDetailResource;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditService;
use App\Services\RBAC\RBACService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function __construct(
        private UserRepository $userRepository,
        private AuditService $auditService,
        private RBACService $rbacService,
    ) {
    }

    /**
     * GET /api/v1/users
     */
    public function index(IndexUserRequest $request): JsonResponse
    {
        $orgId = app('current_organization')->id;
        $users = $this->userRepository->getByOrganization($orgId, perPage: $request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => UserDetailResource::collection($users->items()),
            'meta' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/users
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $orgId = app('current_organization')->id;
        $payload = $request->validated();

        $payload['password'] = Hash::make($payload['password']);
        $payload['name'] = trim($payload['first_name'] . ' ' . $payload['last_name']);

        // Ensure standard users cannot be created as super admins
        $payload['is_super_admin'] = false;

        $user = $this->userRepository->create($payload);

        // Attach to Organization
        $user->organizations()->attach($orgId, [
            'is_owner' => false,
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        if (!empty($payload['roles'])) {
            $roleIds = \App\Models\Role::whereIn('uuid', $payload['roles'])->pluck('id');
            $user->roles()->attach(
                $roleIds->mapWithKeys(fn($roleId) => [$roleId => ['organization_id' => $orgId]])->toArray()
            );
        }

        $this->auditService->logCreated($user);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'data' => new UserDetailResource($this->userRepository->findWithOrgRoles($user->id, $orgId)),
        ], 201);
    }

    /**
     * GET /api/v1/users/{user}
     */
    public function show(User $user): JsonResponse
    {
        $orgId = app('current_organization')->id;

        // Eager load roles for this specific org
        $user = $this->userRepository->findWithOrgRoles($user->id, $orgId);

        if (!$user || !$user->organizations->contains('id', $orgId)) {
            return response()->json(['success' => false, 'message' => 'User not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new UserDetailResource($user),
        ]);
    }

    /**
     * PUT /api/v1/users/{user}
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $orgId = app('current_organization')->id;

        if (!$user->organizations()->where('organizations.id', $orgId)->exists()) {
            return response()->json(['success' => false, 'message' => 'User not found in this organization.'], 404);
        }

        $payload = $request->validated();
        if (!empty($payload['password'])) {
            $payload['password'] = Hash::make($payload['password']);
        } else {
            unset($payload['password']);
        }

        if (isset($payload['first_name']) || isset($payload['last_name'])) {
            $firstName = $payload['first_name'] ?? $user->first_name;
            $lastName = $payload['last_name'] ?? $user->last_name;
            $payload['name'] = trim($firstName . ' ' . $lastName);
        }

        $original = $user->getAttributes();
        $this->userRepository->update($user, $payload);
        $this->auditService->logUpdated($user->fresh(), $original);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => new UserDetailResource($this->userRepository->findWithOrgRoles($user->id, $orgId)),
        ]);
    }

    /**
     * DELETE /api/v1/users/{user}
     */
    public function destroy(User $user): JsonResponse
    {
        $orgId = app('current_organization')->id;

        if (!$user->organizations()->where('organizations.id', $orgId)->exists()) {
            return response()->json(['success' => false, 'message' => 'User not found.'], 404);
        }

        // Technically, you might just want to detach them from the organization instead of deleting the global user model entirely
        $user->organizations()->detach($orgId);

        // Remove roles for this org
        $user->roles()->wherePivot('organization_id', $orgId)->detach();

        $this->auditService->logDeleted($user);

        // Only soft-delete if this was their last organization
        if ($user->organizations()->count() === 0) {
            $this->userRepository->delete($user);
        }

        $this->rbacService->flushCache($user->id, $orgId);

        return response()->json([
            'success' => true,
            'message' => 'User removed from organization successfully.',
        ]);
    }

    /**
     * PUT /api/v1/users/{user}/roles
     */
    public function syncRoles(SyncUserRolesRequest $request, User $user): JsonResponse
    {
        $orgId = app('current_organization')->id;

        if (!$user->organizations()->where('organizations.id', $orgId)->exists()) {
            return response()->json(['success' => false, 'message' => 'User not found.'], 404);
        }

        $roleIds = \App\Models\Role::whereIn('uuid', $request->validated('roles'))->pluck('id');
        $rolesData = $roleIds->mapWithKeys(fn($roleId) => [
            $roleId => ['organization_id' => $orgId]
        ])->toArray();

        // Remove old org roles and attach new ones
        $user->roles()->wherePivot('organization_id', $orgId)->detach();
        $user->roles()->attach($rolesData);

        // Flush RBAC cache
        $this->rbacService->flushCache($user->id, $orgId);

        $this->auditService->logAction('update', $user, 'roles', ['roles' => $request->validated('roles')]);

        return response()->json([
            'success' => true,
            'message' => 'User roles updated successfully.',
            'data' => new UserDetailResource($this->userRepository->findWithOrgRoles($user->id, $orgId)),
        ]);
    }
}
