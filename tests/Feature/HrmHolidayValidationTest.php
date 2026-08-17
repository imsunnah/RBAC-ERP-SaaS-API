<?php

use App\Models\HrmHoliday;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Traits\SetupOrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class, SetupOrganizationContext::class);

beforeEach(function () {
    config(['app.url' => 'http://localhost']);
    $this->withoutMiddleware([\App\Http\Middleware\EnforceBillingPlan::class]);
    $this->orgData = $this->setupOrgUser('Admin', [
        'hrm.holiday.view',
        'hrm.holiday.create',
        'hrm.holiday.update',
        'hrm.holiday.delete',
    ]);
});

test('can add multiple holidays on the same day', function () {
    $date = today()->addDays(5)->format('Y-m-d');

    // First holiday
    $response = $this->actingAsOrgMember($this->orgData['user'], $this->orgData['org'])
        ->postJson('/api/v1/hrm-holidays', [
            'name' => 'Holiday One',
            'date' => $date,
        ]);
    $response->assertStatus(201);

    // Second holiday on same day
    $response = $this->actingAsOrgMember($this->orgData['user'], $this->orgData['org'])
        ->postJson('/api/v1/hrm-holidays', [
            'name' => 'Holiday Two',
            'date' => $date,
        ]);
    $response->assertStatus(201);

    $this->assertDatabaseHas('hrm_holidays', ['name' => 'Holiday One', 'date' => $date]);
    $this->assertDatabaseHas('hrm_holidays', ['name' => 'Holiday Two', 'date' => $date]);
});

test('cannot add holiday on a previous day', function () {
    $pastDate = today()->subDay()->format('Y-m-d');

    $response = $this->actingAsOrgMember($this->orgData['user'], $this->orgData['org'])
        ->postJson('/api/v1/hrm-holidays', [
            'name' => 'Past Holiday',
            'date' => $pastDate,
        ]);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['date']);
});

test('cannot update a past holiday', function () {
    // Manually create a holiday in the past (bypass validation)
    $pastDate = today()->subDays(2)->format('Y-m-d');
    $holiday = HrmHoliday::create([
        'uuid' => (string) Str::uuid(),
        'company_id' => $this->orgData['company']->id,
        'organization_id' => $this->orgData['org']->id,
        'name' => 'Old Holiday',
        'date' => $pastDate,
        'is_active' => true,
    ]);

    // Try updating it
    $response = $this->actingAsOrgMember($this->orgData['user'], $this->orgData['org'])
        ->putJson("/api/v1/hrm-holidays/{$holiday->uuid}", [
            'name' => 'Old Holiday Updated',
        ]);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['date']);
});

test('cannot update a holiday to a past date', function () {
    $futureDate = today()->addDays(2)->format('Y-m-d');
    $pastDate = today()->subDays(2)->format('Y-m-d');

    $holiday = HrmHoliday::create([
        'uuid' => (string) Str::uuid(),
        'company_id' => $this->orgData['company']->id,
        'organization_id' => $this->orgData['org']->id,
        'name' => 'Future Holiday',
        'date' => $futureDate,
        'is_active' => true,
    ]);

    // Try updating date to a past date
    $response = $this->actingAsOrgMember($this->orgData['user'], $this->orgData['org'])
        ->putJson("/api/v1/hrm-holidays/{$holiday->uuid}", [
            'date' => $pastDate,
        ]);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['date']);
});

test('bulk update cannot add holiday for previous day', function () {
    $pastDate = today()->subDay()->format('Y-m-d');

    $response = $this->actingAsOrgMember($this->orgData['user'], $this->orgData['org'])
        ->postJson('/api/v1/hrm-holidays/by-date', [
            'date' => $pastDate,
            'holidays' => [
                ['name' => 'Bulk Past Holiday']
            ]
        ]);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['date']);
});
