<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\User\CreateUserAction;
use App\Actions\User\DeleteUserAction;
use App\Actions\User\SyncUserRolesAction;
use App\Actions\User\UpdateUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\IndexUserRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\SyncUserRolesRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\User\UserDetailResource;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function __construct(
        private UserRepository $userRepository
    ) {}

    /** GET /api/v1/users */
    public function index(IndexUserRequest $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $orgId = app('current_organization')->id;
        $users = $this->userRepository->getByOrganization($orgId, perPage: $request->input('per_page', 20));

        $users->getCollection()->load(['roles' => fn ($q) => $q->wherePivot('organization_id', $orgId)]);

        return response()->json([
            'success' => true,
            'data'    => UserDetailResource::collection($users->items()),
            'meta'    => [
                'total'        => $users->total(),
                'per_page'     => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
            ],
        ]);
    }

    /** POST /api/v1/users */
    public function store(StoreUserRequest $request, CreateUserAction $action): JsonResponse
    {
        $this->authorize('create', User::class);

        $orgId = app('current_organization')->id;
        $user  = $action->execute($request->validated(), $orgId);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'data'    => new UserDetailResource($user),
        ], 201);
    }

    /** GET /api/v1/users/{user} */
    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $orgId = app('current_organization')->id;
        $user  = $this->userRepository->findWithOrgRoles($user->id, $orgId);

        if (!$user || !$user->organizations->contains('id', $orgId)) {
            return response()->json(['success' => false, 'message' => 'User not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => new UserDetailResource($user),
        ]);
    }

    /** PUT /api/v1/users/{user} */
    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $action): JsonResponse
    {
        $this->authorize('update', $user);

        $orgId = app('current_organization')->id;

        if (!$user->organizations()->where('organizations.id', $orgId)->exists()) {
            return response()->json(['success' => false, 'message' => 'User not found in this organization.'], 404);
        }

        $updatedUser = $action->execute($user, $request->validated(), $orgId);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data'    => new UserDetailResource($updatedUser),
        ]);
    }

    /** DELETE /api/v1/users/{user} */
    public function destroy(User $user, DeleteUserAction $action): JsonResponse
    {
        $this->authorize('delete', $user);

        $orgId = app('current_organization')->id;

        if (!$user->organizations()->where('organizations.id', $orgId)->exists()) {
            return response()->json(['success' => false, 'message' => 'User not found.'], 404);
        }

        $action->execute($user, $orgId);

        return response()->json([
            'success' => true,
            'message' => 'User removed from organization successfully.',
        ]);
    }

    /** PUT /api/v1/users/{user}/roles */
    public function syncRoles(SyncUserRolesRequest $request, User $user, SyncUserRolesAction $action): JsonResponse
    {
        $this->authorize('update', $user);

        $orgId = app('current_organization')->id;

        if (!$user->organizations()->where('organizations.id', $orgId)->exists()) {
            return response()->json(['success' => false, 'message' => 'User not found.'], 404);
        }

        $user = $action->execute($user, $request->validated('roles'), $orgId);

        return response()->json([
            'success' => true,
            'message' => 'User roles updated successfully.',
            'data'    => new UserDetailResource($user),
        ]);
    }
}
