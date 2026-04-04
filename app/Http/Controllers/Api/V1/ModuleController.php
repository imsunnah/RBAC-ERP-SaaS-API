<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Module\ModuleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    /**
     * GET /api/v1/modules
     * Lists all active modules that belong to the current organization's subscription.
     */
    public function index(Request $request): JsonResponse
    {
        $organization = app('current_organization');

        // Fetch modules legally tied directly to the organization
        $modules = $organization->activeModules()
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => ModuleResource::collection($modules),
        ]);
    }
}
