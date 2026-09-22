<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Resources\CompanyResource;
use Illuminate\Support\Facades\Auth;

class CompanyController extends Controller
{
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $companies = Company::with('organizations');

        if (!$user->is_super_admin) {
            $companies->whereKey($user->company_id);
        }

        return response()->json([
            'success' => true,
            'data' => CompanyResource::collection($companies->get()),
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
