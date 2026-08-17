<?php

use App\Models\Module;
use App\Models\Organization;
use Illuminate\Support\Str;
use Tests\Traits\SetupOrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class, SetupOrganizationContext::class);

beforeEach(function () {
    config(['app.url' => 'http://localhost']);
    $this->withoutMiddleware([\App\Http\Middleware\EnforceBillingPlan::class]);
    $this->orgData = $this->setupOrgUser('Admin', [
        'admin.module.create',
        'admin.module.update',
        'admin.module.view',
    ]);
    
    // Set user as super admin since ModuleRequest/ModuleUpdateRequest authorize requires it
    $this->orgData['user']->is_super_admin = true;
    $this->orgData['user']->save();
});

test('can create a module and associate multiple organizations', function () {
    $org1 = Organization::create([
        'uuid' => (string) Str::uuid(),
        'company_id' => $this->orgData['company']->id,
        'name' => 'Org One',
        'slug' => 'org-one',
    ]);

    $org2 = Organization::create([
        'uuid' => (string) Str::uuid(),
        'company_id' => $this->orgData['company']->id,
        'name' => 'Org Two',
        'slug' => 'org-two',
    ]);

    $payload = [
        'name' => 'New Test Module',
        'slug' => 'new-test-module',
        'description' => 'Test description',
        'url' => '/test-url',
        'is_core' => false,
        'sort_order' => 10,
        'organizations' => [$org1->uuid, $org2->uuid],
    ];

    $response = $this->actingAsOrgMember($this->orgData['user'], $this->orgData['org'])
        ->postJson('/api/v1/modules', $payload);

    $response->assertStatus(201);
    $response->assertJsonPath('data.name', 'New Test Module');
    $response->assertJsonCount(2, 'data.organizations');

    $module = Module::where('slug', 'new-test-module')->first();
    expect($module->organizations)->toHaveCount(2);
});

test('can update a module and sync organizations', function () {
    $org1 = Organization::create([
        'uuid' => (string) Str::uuid(),
        'company_id' => $this->orgData['company']->id,
        'name' => 'Org One',
        'slug' => 'org-one',
    ]);

    $org2 = Organization::create([
        'uuid' => (string) Str::uuid(),
        'company_id' => $this->orgData['company']->id,
        'name' => 'Org Two',
        'slug' => 'org-two',
    ]);

    $module = Module::create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Existing Module',
        'slug' => 'existing-module',
        'url' => '/existing',
        'is_core' => false,
        'sort_order' => 5,
    ]);

    // Attach org1 initially
    $module->organizations()->attach($org1->id, [
        'uuid' => (string) Str::uuid(),
        'is_active' => true,
        'activated_at' => now(),
    ]);

    $payload = [
        'name' => 'Updated Module Name',
        'organizations' => [$org2->uuid], // Sync only org2
    ];

    $response = $this->actingAsOrgMember($this->orgData['user'], $this->orgData['org'])
        ->putJson("/api/v1/modules/{$module->uuid}", $payload);

    $response->assertStatus(200);
    $response->assertJsonPath('data.name', 'Updated Module Name');
    $response->assertJsonCount(1, 'data.organizations');
    $response->assertJsonPath('data.organizations.0.id', $org2->uuid);

    $module->refresh();
    expect($module->organizations)->toHaveCount(1);
    expect($module->organizations->first()->id)->toBe($org2->id);
});
