<?php

use App\Models\HrmEmployee;
use App\Models\HrmLookup;
use App\Models\HrmLookupGroup;
use Illuminate\Support\Str;
use Tests\Traits\SetupOrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class, SetupOrganizationContext::class);

beforeEach(function () {
    config(['app.url' => 'http://localhost']);
    $this->withoutMiddleware([\App\Http\Middleware\EnforceBillingPlan::class]);
    $this->orgData = $this->setupOrgUser('Admin', [
        'hrm.employee.view',
    ]);
});

test('can retrieve team members from same department excluding self', function () {
    // Create department
    $group = HrmLookupGroup::create([
        'uuid' => (string) Str::uuid(),
        'company_id' => $this->orgData['company']->id,
        'organization_id' => $this->orgData['org']->id,
        'name' => 'Department',
        'slug' => 'department',
        'is_active' => true,
    ]);

    $department = HrmLookup::create([
        'uuid' => (string) Str::uuid(),
        'company_id' => $this->orgData['company']->id,
        'organization_id' => $this->orgData['org']->id,
        'hrm_lookup_group_id' => $group->id,
        'name' => 'Engineering',
        'slug' => 'engineering',
        'is_active' => true,
    ]);

    $otherDepartment = HrmLookup::create([
        'uuid' => (string) Str::uuid(),
        'company_id' => $this->orgData['company']->id,
        'organization_id' => $this->orgData['org']->id,
        'hrm_lookup_group_id' => $group->id,
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);

    // Create current employee (attached to user)
    $employeeSelf = HrmEmployee::create([
        'uuid' => (string) Str::uuid(),
        'company_id' => $this->orgData['company']->id,
        'organization_id' => $this->orgData['org']->id,
        'full_name' => 'Self Employee',
        'department_id' => $department->id,
        'is_active' => 1,
    ]);

    $this->orgData['user']->employee_id = $employeeSelf->id;
    $this->orgData['user']->save();

    // Create teammate (same department)
    $employeeTeammate = HrmEmployee::create([
        'uuid' => (string) Str::uuid(),
        'company_id' => $this->orgData['company']->id,
        'organization_id' => $this->orgData['org']->id,
        'full_name' => 'Teammate Employee',
        'department_id' => $department->id,
        'is_active' => 1,
    ]);

    // Create other employee (different department)
    $employeeOther = HrmEmployee::create([
        'uuid' => (string) Str::uuid(),
        'company_id' => $this->orgData['company']->id,
        'organization_id' => $this->orgData['org']->id,
        'full_name' => 'Other Employee',
        'department_id' => $otherDepartment->id,
        'is_active' => 1,
    ]);

    $response = $this->actingAsOrgMember($this->orgData['user'], $this->orgData['org'])
        ->getJson('/api/v1/hrm-employees/my-team-members');

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data.data');
    $response->assertJsonPath('data.data.0.uuid', $employeeTeammate->uuid);
    $response->assertJsonPath('data.data.0.full_name', 'Teammate Employee');
});

test('returns empty if employee has no department', function () {
    $employeeSelf = HrmEmployee::create([
        'uuid' => (string) Str::uuid(),
        'company_id' => $this->orgData['company']->id,
        'organization_id' => $this->orgData['org']->id,
        'full_name' => 'Self Employee No Dept',
        'is_active' => 1,
    ]);

    $this->orgData['user']->employee_id = $employeeSelf->id;
    $this->orgData['user']->save();

    $response = $this->actingAsOrgMember($this->orgData['user'], $this->orgData['org'])
        ->getJson('/api/v1/hrm-employees/my-team-members');

    $response->assertStatus(200);
    $response->assertJsonCount(0, 'data');
});

test('returns 404 if user has no employee record', function () {
    $this->orgData['user']->employee_id = null;
    $this->orgData['user']->save();

    $response = $this->actingAsOrgMember($this->orgData['user'], $this->orgData['org'])
        ->getJson('/api/v1/hrm-employees/my-team-members');

    $response->assertStatus(404);
});

test('can search team members by name', function () {
    // Create department
    $group = HrmLookupGroup::create([
        'uuid' => (string) Str::uuid(),
        'company_id' => $this->orgData['company']->id,
        'organization_id' => $this->orgData['org']->id,
        'name' => 'Department',
        'slug' => 'department',
        'is_active' => true,
    ]);

    $department = HrmLookup::create([
        'uuid' => (string) Str::uuid(),
        'company_id' => $this->orgData['company']->id,
        'organization_id' => $this->orgData['org']->id,
        'hrm_lookup_group_id' => $group->id,
        'name' => 'Engineering',
        'slug' => 'engineering',
        'is_active' => true,
    ]);

    // Create current employee
    $employeeSelf = HrmEmployee::create([
        'uuid' => (string) Str::uuid(),
        'company_id' => $this->orgData['company']->id,
        'organization_id' => $this->orgData['org']->id,
        'full_name' => 'Self Employee',
        'department_id' => $department->id,
        'is_active' => 1,
    ]);
    $this->orgData['user']->employee_id = $employeeSelf->id;
    $this->orgData['user']->save();

    // Create teammates
    HrmEmployee::create([
        'uuid' => (string) Str::uuid(),
        'company_id' => $this->orgData['company']->id,
        'organization_id' => $this->orgData['org']->id,
        'full_name' => 'Alice Cooper',
        'department_id' => $department->id,
        'is_active' => 1,
    ]);

    HrmEmployee::create([
        'uuid' => (string) Str::uuid(),
        'company_id' => $this->orgData['company']->id,
        'organization_id' => $this->orgData['org']->id,
        'full_name' => 'Bob Marley',
        'department_id' => $department->id,
        'is_active' => 1,
    ]);

    $response = $this->actingAsOrgMember($this->orgData['user'], $this->orgData['org'])
        ->getJson('/api/v1/hrm-employees/my-team-members?search=Alice');

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data.data');
    $response->assertJsonPath('data.data.0.full_name', 'Alice Cooper');
});
