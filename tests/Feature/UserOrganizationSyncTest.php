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

test('can update user organizations list', function () {
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
        'first_name' => 'John',
        'last_name' => 'Doe',
        'organizations' => [$otherOrg->uuid], // sync to otherOrg
    ];

    $response = $this->actingAsOrgMember($this->orgData['user'], $this->orgData['org'])
        ->putJson("/api/v1/users/{$userToUpdate->uuid}", $payload);

    $response->assertStatus(200);
    $response->assertJsonPath('data.first_name', 'John');

    $userToUpdate->refresh();
    // Check that otherOrg is attached
    expect($userToUpdate->organizations->contains('id', $otherOrg->id))->toBeTrue();
});
