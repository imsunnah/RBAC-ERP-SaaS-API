<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Role\SyncRoleAllPermissionsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Role\DestroyRoleRequest;
use App\Http\Requests\Role\IndexRoleRequest;
use App\Http\Requests\Role\ShowRoleRequest;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\SyncRoleDataRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Http\Resources\SyncRoleResource;
use App\Models\Role;
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
    ) {}

    /** GET /api/v1/roles */
    public function index(IndexRoleRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $orgId = app('current_organization')->id;
        $roles = $this->roleRepository->getByOrganization($orgId, perPage: 20);

        return response()->json([
            'success' => true,
            'data'    => RoleResource::collection($roles->items()),
            'meta'    => [
                'total'        => $roles->total(),
                'per_page'     => $roles->perPage(),
                'current_page' => $roles->currentPage(),
                'last_page'    => $roles->lastPage(),
            ],
        ]);
    }

    /** POST /api/v1/roles */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $this->authorize('create', Role::class);

        $orgId = app('current_organization')->id;

        $role = $this->roleRepository->create(array_merge(
            $request->validated(),
            ['organization_id' => $orgId]
        ));

        $this->auditService->logCreated($role);

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully.',
            'data'    => new RoleResource($role),
        ], 201);
    }

    /** GET /api/v1/roles/{id} */
    public function show(ShowRoleRequest $request, Role $role): JsonResponse
    {
        $this->authorize('view', $role);

        $orgId = app('current_organization')->id;

        if ((int) $role->organization_id !== (int) $orgId) {
            return response()->json(['success' => false, 'message' => 'Role not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => new RoleResource($role->load(['permissions', 'modules', 'menus', 'submenus'])),
        ]);
    }

    /** PUT /api/v1/roles/{id} */
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $orgId = app('current_organization')->id;

        if ((int) $role->organization_id !== (int) $orgId) {
            return response()->json(['success' => false, 'message' => 'Role not found.'], 404);
        }

        $original = $role->getAttributes();
        $this->roleRepository->update($role, $request->validated());
        $this->auditService->logUpdated($role->fresh(), $original);

        $role->users->each(fn ($u) => $this->rbacService->flushCache($u->id, $u->pivot->organization_id));

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully.',
            'data'    => new RoleResource($role->fresh()),
        ]);
    }

    /** DELETE /api/v1/roles/{id} */
    public function destroy(DestroyRoleRequest $request, Role $role): JsonResponse
    {
        $this->authorize('delete', $role);

        $orgId = app('current_organization')->id;

        if ((int) $role->organization_id !== (int) $orgId) {
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
     * PUT /api/v1/syncRole/{role}
     * Sync modules, menus, submenus, and permissions to a role in one request.
     */
    public function syncAll(SyncRoleDataRequest $request, Role $role, SyncRoleAllPermissionsAction $action): JsonResponse
    {
        $this->authorize('update', $role);

        $orgId = app('current_organization')->id;

        if ((int) $role->organization_id !== (int) $orgId) {
            return response()->json(['success' => false, 'message' => 'Role not found.'], 404);
        }

        $action->execute($role, $request->validated(), $orgId);

        return response()->json([
            'success' => true,
            'message' => 'Role synced successfully.',
        ]);
    }

    /** Fetch every role with its menus, submenus, permissions, and modules. */
    public function roleListData(): JsonResponse
    {
        $orgId = app('current_organization')->id;

        $roles = Role::where('organization_id', $orgId)
            ->with(['menus', 'submenus', 'permissions', 'modules'])
            ->get();

        return response()->json([
            'success' => true,
            'data'    => SyncRoleResource::collection($roles),
        ]);
    }
}
