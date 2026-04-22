<?php

use App\Models\User;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Module;
use App\Models\Permission;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([\App\Http\Middleware\EnforceBillingPlan::class]);

    $this->org = Organization::create([
        'uuid' => Str::uuid()->toString(),
        'name' => 'Test Org',
        'slug' => 'test-org-' . Str::random(5),
        'status' => 'active',
    ]);

    $this->user = User::factory()->create();
    
    $this->user->organizations()->attach($this->org->id, [
        'is_owner' => true,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    // Give super admin so it has all permissions inherently in most RBAC
    $this->user->is_super_admin = true;
    $this->user->save();

    Passport::actingAs($this->user);
});

test('can retrieve modules', function () {
    $response = $this->getJson('/api/v1/modules', [
        'X-ORG-ID' => $this->org->uuid,
    ]);
    $response->assertStatus(200)->assertJsonStructure(['success', 'data']);
});

test('can retrieve permissions', function () {
    $response = $this->getJson('/api/v1/permissions', [
        'X-ORG-ID' => $this->org->uuid,
    ]);
    $response->assertStatus(200)->assertJsonStructure(['success', 'data']);
});

test('can retrieve roles', function () {
    $response = $this->getJson('/api/v1/roles', [
        'X-ORG-ID' => $this->org->uuid,
    ]);
    $response->assertStatus(200)->assertJsonStructure(['success', 'data']);
});

test('can create a role', function () {
    $response = $this->postJson('/api/v1/roles', [
        'name' => 'Test Role',
        'slug' => 'test-role-' . Str::random(5),
        'status' => 'active',
    ], [
        'X-ORG-ID' => $this->org->uuid,
    ]);
    $response->assertStatus(201)->assertJson(['success' => true]);
});

test('can retrieve users', function () {
    $response = $this->getJson('/api/v1/users', [
        'X-ORG-ID' => $this->org->uuid,
    ]);
    $response->assertStatus(200)->assertJsonStructure(['success', 'data']);
});

test('can retrieve sidebar', function () {
    $response = $this->getJson('/api/v1/sidebar', [
        'X-ORG-ID' => $this->org->uuid,
    ]);
    $response->assertStatus(200)->assertJsonStructure(['success', 'data']);
});
