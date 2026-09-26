<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StoreTeamRequest;
use App\Http\Requests\Team\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\HrmEmployee;
use App\Models\HrmLookup;
use App\Models\TeamChd;
use App\Models\TeamMst;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min(max($request->integer('limit', 15), 1), 100);
        $query = TeamMst::with(['employee', 'teamChd.department'])->orderBy('team_name');

        if ($request->filled('active_status')) {
            $query->where('active_status', $request->boolean('active_status'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($limit)->through(
                fn (TeamMst $team) => (new TeamResource($team))->resolve()
            ),
        ]);
    }

    public function store(StoreTeamRequest $request)
    {
        $organization = app('current_organization');

        $team = DB::transaction(function () use ($request, $organization) {

            $employee = HrmEmployee::where(
                'uuid',
                $request->hrm_employee_id
            )->firstOrFail();

            $data = $request->safe()->except(['department', 'hrm_employee_id']);

            $team = TeamMst::create([
                ...$data,
                'hrm_employee_id' => $employee->id,
                'team_lead_name' => $employee->full_name,
                'created_by' => $request->user()->id,
                'company_id' => $organization->company_id,
                'organization_id' => $organization->id,
            ]);

            foreach ($request->validated()['department'] ?? [] as $departmentId) {
                $departmentId = HrmLookup::where('uuid', $departmentId)->pluck('id')->first();
                TeamChd::create([
                    'team_mst_id' => $team->id,
                    'department_id' => $departmentId,
                    'created_by' => $request->user()->id,
                ]);
            }

            return $team;
        });

        return response()->json([
            'success' => true,
            'message' => 'Team created successfully.',
            'data' => (new TeamResource($team->load(['employee', 'teamChd.department'])))->resolve(),
        ], 201);
    }

    public function show(TeamMst $team): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => (new TeamResource($team->load(['employee', 'teamChd.department'])))->resolve(),
        ]);
    }

    /**
     * update teams data
     */
    public function update(UpdateTeamRequest $request, TeamMst $team): JsonResponse
    {
        $organization = app('current_organization');

        DB::transaction(function () use ($request, $team, $organization) {

            $employee = HrmEmployee::where(
                'uuid',
                $request->hrm_employee_id
            )->firstOrFail();


            $data = $request->safe()->except([
                'department',
                'hrm_employee_id',
            ]);

            $team->update([
                ...$data,
                'hrm_employee_id' => $employee->id,
                'team_lead_name' => $employee->full_name,
                'updated_by' => $request->user()->id,
                'company_id' => $organization->company_id,
                'organization_id' => $organization->id,
            ]);


            foreach ($request->validated()['department'] ?? [] as $department) {

                // If child ID exists, update existing child
                if (!empty($department['id'])) {

                    $teamChd = TeamChd::where('uuid', $department['id'])
                        ->where('team_mst_id', $team->id)
                        ->first();

                    if ($teamChd) {

                        $teamChd->update([
                            'department_id' => HrmLookup::where('uuid', $department['department_id'])
                                ->firstOrFail()->id,
                            'active_status' => $department['active_status'] ?? true,
                            'updated_by' => $request->user()->id,
                        ]);
                    }
                } else {

                    // No ID means this is a new department
                    TeamChd::create([
                        'team_mst_id' => $team->id,
                        'department_id' => HrmLookup::where('uuid', $department['department_id'])
                            ->firstOrFail()->id,
                        'active_status' => $department['active_status'] ?? true,
                        'created_by' => $request->user()->id,
                    ]);
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Team updated successfully.',
            'data' => (new TeamResource($team->fresh()->load(['employee', 'teamChd.department'])))->resolve(),
        ]);
    }


    public function destroy(TeamMst $team): JsonResponse
    {
        $team->delete();

        return response()->json([
            'success' => true,
            'message' => 'Team deleted successfully.',
        ]);
    }
}
