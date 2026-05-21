<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Resources\CompanyResource;

class CompanyController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => CompanyResource::collection(Company::with('organizations')->get()),
        ]);
    }

    public function show(Company $company): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new CompanyResource($company->load('organizations')),
        ]);
    }
}
