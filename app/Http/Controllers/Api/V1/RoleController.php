<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Role\DestroyRoleRequest;
use App\Http\Requests\Role\IndexRoleRequest;
use App\Http\Requests\Role\ShowRoleRequest;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\SyncRoleModulesRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Repositories\RoleRepository;
use App\Services\Audit\AuditService;
use App\Services\RBAC\RBACService;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function __construct(
        private RoleRepository $roleRepository,
        private AuditService $auditService,
        private RBACService $rbacService,
    ) {
    }

    /**
     * GET /api/v1/roles
     */
    public function index(IndexRoleRequest $request): JsonResponse
    {
        $orgId = app('current_organization')->id;
        $roles = $this->roleRepository->getByOrganization($orgId, perPage: 20);

        return response()->json([
            'success' => true,
            'data' => RoleResource::collection($roles->items()),
            'meta' => [
                'total' => $roles->total(),
                'per_page' => $roles->perPage(),
                'current_page' => $roles->currentPage(),
                'last_page' => $roles->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/roles
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $orgId = app('current_organization')->id;

        $payload = $request->validated();
        if (!empty($payload['parent_role_id'])) {
            $parent = Role::where('uuid', $payload['parent_role_id'])->first();
            $payload['parent_role_id'] = $parent?->id;
        }

        $role = $this->roleRepository->create(array_merge(
            $payload,
            ['organization_id' => $orgId]
        ));

        $this->auditService->logCreated($role);

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully.',
            'data' => new RoleResource($role),
        ], 201);
    }

    /**
     * GET /api/v1/roles/{id}
     */
    public function show(ShowRoleRequest $request, Role $role): JsonResponse
    {
        $orgId = app('current_organization')->id;

        if ($role->organization_id !== $orgId) {
            return response()->json(['success' => false, 'message' => 'Role not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new RoleResource($role->load(['permissions', 'modules', 'menus', 'submenus'])),
        ]);
    }

    /**
     * PUT /api/v1/roles/{id}
     */
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $orgId = app('current_organization')->id;

        if ($role->organization_id !== $orgId) {
            return response()->json(['success' => false, 'message' => 'Role not found.'], 404);
        }

        $payload = $request->validated();
        if (!empty($payload['parent_role_id'])) {
            $parent = Role::where('uuid', $payload['parent_role_id'])->first();
            $payload['parent_role_id'] = $parent?->id;
        }

        $original = $role->getAttributes();
        $this->roleRepository->update($role, $payload);
        $this->auditService->logUpdated($role->fresh(), $original);

        // Flush permission caches for all users with this role
        $role->users->each(fn($u) => $this->rbacService->flushCache($u->id, $orgId));

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully.',
            'data' => new RoleResource($role->fresh()),
        ]);
    }

    /**
     * DELETE /api/v1/roles/{id}
     */
    public function destroy(DestroyRoleRequest $request, Role $role): JsonResponse
    {
        $orgId = app('current_organization')->id;

        if ($role->organization_id !== $orgId) {
            return response()->json(['success' => false, 'message' => 'Role not found.'], 404);
        }

        $this->auditService->logDeleted($role);
        $this->roleRepository->delete($role);

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully.',
        ]);
    }

    /**
     * PUT /api/v1/roles/{id}/modules
     */
    public function syncModules(SyncRoleModulesRequest $request, Role $role): JsonResponse
    {
        $orgId = app('current_organization')->id;

        if ($role->organization_id !== $orgId) {
            return response()->json(['success' => false, 'message' => 'Role not found.'], 404);
        }

        $modules = $request->validated('modules');
        $moduleIds = \App\Models\Module::whereIn('uuid', $modules)->pluck('id');

        // Sync modules natively
        $role->modules()->sync($moduleIds);

        // Clear RBAC Cache across users mapped to this Role
        $role->users->each(fn($u) => $this->rbacService->flushCache($u->id, $orgId));

        $this->auditService->logAction('update', $role, 'role_modules', $modules);

        return response()->json([
            'success' => true,
            'message' => 'Role modules assigned successfully.',
        ]);
    }
}
