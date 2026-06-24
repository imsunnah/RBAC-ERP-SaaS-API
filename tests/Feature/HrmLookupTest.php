<?php

namespace Tests\Feature;

use App\Models\HrmLookup;
use App\Models\HrmLookupGroup;
use Tests\Traits\SetupOrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class, SetupOrganizationContext::class);

test('authorized user can create a lookup group', function () {
    $org = $this->setupOrgUser('Manager', ['admin.hrm-lookup.create']);

    $response = $this->actingAsOrgMember($org['user'], $org['org'])
        ->postJson('/api/v1/hrm-lookup-groups', [
            'name' => 'Department',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Department');

    $this->assertDatabaseHas('hrm_lookup_groups', ['name' => 'Department', 'organization_id' => $org['org']->id]);
});

test('authorized user can create a lookup item in a group', function () {
    $org = $this->setupOrgUser('Manager', ['admin.hrm-lookup.create', 'admin.hrm-lookup.view']);
    $group = HrmLookupGroup::factory()->create(['organization_id' => $org['org']->id, 'name' => 'Department']);

    $response = $this->actingAsOrgMember($org['user'], $org['org'])
        ->postJson('/api/v1/hrm-lookups', [
            'hrm_lookup_group_id' => $group->uuid,
            'name' => 'IT Department',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'IT Department');
});

test('user can list lookup items by group slug', function () {
    $org = $this->setupOrgUser('Manager', ['admin.hrm-lookup.view']);
    $group = HrmLookupGroup::factory()->create(['organization_id' => $org['org']->id, 'name' => 'Department', 'slug' => 'department']);

    HrmLookup::factory()->create([
        'hrm_lookup_group_id' => $group->id,
        'name' => 'IT',
        'organization_id' => $org['org']->id
    ]);

    $response = $this->actingAsOrgMember($org['user'], $org['org'])
        ->getJson('/api/v1/hrm-lookups?group_slug=department');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data.data');
});

test('user cannot see lookups from another organization', function () {
    $orgA = $this->setupOrgUser('Manager', ['admin.hrm-lookup.view']);
    $orgB = $this->setupOrgUser('Manager', ['admin.hrm-lookup.view']);

    $groupA = HrmLookupGroup::factory()->create(['organization_id' => $orgA['org']->id, 'name' => 'Dept A', 'slug' => 'dept-a']);
    $groupB = HrmLookupGroup::factory()->create(['organization_id' => $orgB['org']->id, 'name' => 'Dept B', 'slug' => 'dept-b']);

    $lookupB = HrmLookup::factory()->create([
        'hrm_lookup_group_id' => $groupB->id,
        'name' => 'Org B IT',
        'organization_id' => $orgB['org']->id
    ]);

    $response = $this->actingAsOrgMember($orgA['user'], $orgA['org'])
        ->getJson('/api/v1/hrm-lookups?group_slug=dept-a');

    $response->assertStatus(200)
        ->assertJsonCount(0, 'data.data');

    $this->actingAsOrgMember($orgA['user'], $orgA['org'])
        ->getJson("/api/v1/hrm-lookups/{$lookupB->uuid}")
        ->assertStatus(404);
});
