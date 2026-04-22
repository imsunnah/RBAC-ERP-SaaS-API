<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sidebar\IndexSidebarRequest;
use App\Http\Resources\Sidebar\SidebarResource;
use App\Services\Sidebar\SidebarService;
use Illuminate\Http\JsonResponse;

class SidebarController extends Controller
{
    public function __construct(private SidebarService $sidebarService)
    {
    }

    /**
     * GET /api/v1/sidebar
     * Returns the role-filtered, module-scoped sidebar for the current user + organization.
     * Optional: ?module={slug} to filter sidebar to a specific module.
     */
    public function index(IndexSidebarRequest $request): JsonResponse
    {
        $user = $request->user();
        $organization = app('current_organization');
        $moduleSlug = $request->query('module');

        $sidebar = $this->sidebarService->build($user, $organization, $moduleSlug);

        return response()->json([
            'success' => true,
            'data' => SidebarResource::collection($sidebar),
        ]);
    }
}
