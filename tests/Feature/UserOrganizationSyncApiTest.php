<?php

use App\Models\User;
use App\Models\Organization;
use Illuminate\Support\Str;
use Tests\Traits\SetupOrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class, SetupOrganizationContext::class);

beforeEach(function () {
    config(['app.url' => 'http://localhost']);
    $this->withoutMiddleware([\App\Http\Middleware\EnforceBillingPlan::class]);
    $this->orgData = $this->setupOrgUser('Admin', [
        'admin.user.view',
        'admin.user.update',
    ]);
});

test('can sync user organizations list via dedicated endpoint', function () {
    $otherOrg = Organization::create([
        'uuid' => (string) Str::uuid(),
        'company_id' => $this->orgData['company']->id,
        'name' => 'Other Organization',
        'slug' => 'other-organization',
    ]);

    // Retrieve active user
    $userToUpdate = User::factory()->create([
        'uuid' => (string) Str::uuid(),
        'company_id' => $this->orgData['company']->id,
    ]);

    // Attach to current org first
    $userToUpdate->organizations()->attach($this->orgData['org']->id, [
        'is_owner' => false,
        'is_primary' => true,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $payload = [
        'organizations' => [$otherOrg->uuid], // sync to otherOrg
    ];

    $response = $this->actingAsOrgMember($this->orgData['user'], $this->orgData['org'])
        ->putJson("/api/v1/users/{$userToUpdate->uuid}/organizations", $payload);

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $response->assertJsonStructure(['success', 'message', 'data']);

    $userToUpdate->refresh();
    // Check that otherOrg is attached
    expect($userToUpdate->organizations->contains('id', $otherOrg->id))->toBeTrue();
    // Check that the current org (orgId) remains attached since it's the active org scope
    expect($userToUpdate->organizations->contains('id', $this->orgData['org']->id))->toBeTrue();
});
