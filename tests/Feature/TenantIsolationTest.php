<?php

namespace Tests\Feature;

use App\Models\HrmEmployee;
use App\Models\Role;
use App\Models\Organization;
use App\Models\User;
use Tests\Traits\SetupOrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class, SetupOrganizationContext::class);

test('user from org A cannot see employees from org B', function () {
    // Setup Org A
    $orgA = $this->setupOrgUser('Manager', ['hrm.employee.view']);
    $employeeA = HrmEmployee::factory()->create(['organization_id' => $orgA['org']->id]);

    // Setup Org B
    $orgB = $this->setupOrgUser('Manager', ['hrm.employee.view']);
    $employeeB = HrmEmployee::factory()->create(['organization_id' => $orgB['org']->id]);

    // Act as Org A user
    $response = $this->actingAsOrgMember($orgA['user'], $orgA['org'])
        ->getJson('/api/v1/hrm-employees');


    $response->assertStatus(200);

    // Try to access Org B employee by UUID directly
    $this->actingAsOrgMember($orgA['user'], $orgA['org'])
        ->getJson("/api/v1/hrm-employees/{$employeeB->uuid}")
        ->assertStatus(404); // Should be 404 because of global scope + route binding
});

test('user from org A cannot update employees from org B', function () {
    $orgA = $this->setupOrgUser('Manager', ['hrm.employee.update']);
    $orgB = $this->setupOrgUser('Manager', ['hrm.employee.update']);
    $employeeB = HrmEmployee::factory()->create(['organization_id' => $orgB['org']->id]);

    $this->actingAsOrgMember($orgA['user'], $orgA['org'])
        ->putJson("/api/v1/hrm-employees/{$employeeB->uuid}", [
            'full_name' => 'Hacked Name'
        ])
        ->assertStatus(404);
});

test('user from org A cannot delete employees from org B', function () {
    $orgA = $this->setupOrgUser('Manager', ['hrm.employee.delete']);
    $orgB = $this->setupOrgUser('Manager', ['hrm.employee.delete']);
    $employeeB = HrmEmployee::factory()->create(['organization_id' => $orgB['org']->id]);

    $this->actingAsOrgMember($orgA['user'], $orgA['org'])
        ->deleteJson("/api/v1/hrm-employees/{$employeeB->uuid}")
        ->assertStatus(404);
});

test('user without permissions receives 403 even if org is correct', function () {
    $orgA = $this->setupOrgUser('Restricted'); // No permissions
    $employeeA = HrmEmployee::factory()->create(['organization_id' => $orgA['org']->id]);

    $this->actingAsOrgMember($orgA['user'], $orgA['org'])
        ->getJson("/api/v1/hrm-employees")
        ->assertStatus(403);

    $this->actingAsOrgMember($orgA['user'], $orgA['org'])
        ->getJson("/api/v1/hrm-employees/{$employeeA->uuid}")
        ->assertStatus(403);
});

test('user from org A cannot see roles from org B', function () {
    $orgA = $this->setupOrgUser('Admin', ['admin.role.view']);
    $orgB = $this->setupOrgUser('Admin', ['admin.role.view']);

    // Role B created in Org B
    $roleB = Role::factory()->create(['organization_id' => $orgB['org']->id, 'name' => 'Org B Role']);

    // Act as Org A user
    $this->actingAsOrgMember($orgA['user'], $orgA['org'])
        ->getJson('/api/v1/roles')
        ->assertStatus(200)
        ->assertJsonMissing(['name' => 'Org B Role']);

    // Try to access Org B role directly
    $this->actingAsOrgMember($orgA['user'], $orgA['org'])
        ->getJson("/api/v1/roles/{$roleB->uuid}")
        ->assertStatus(404);
});
