<?php

namespace Tests\Feature;

use App\Models\GlobalLookup;
use App\Models\GlobalLookupGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Traits\SetupOrganizationContext;

uses(RefreshDatabase::class, SetupOrganizationContext::class);

test('user can get global lookup data by group slug path', function () {
    $org = $this->setupOrgUser('Manager', ['admin.global-lookup.view']);
    $group = GlobalLookupGroup::create([
        'organization_id' => $org['org']->id,
        'name' => 'Country',
        'slug' => 'country',
    ]);

    GlobalLookup::create([
        'global_lookup_group_id' => $group->id,
        'organization_id' => $org['org']->id,
        'name' => 'Bangladesh',
        'slug' => 'bangladesh',
    ]);

    $this->actingAsOrgMember($org['user'], $org['org'])
        ->getJson('/api/v1/global-lookups/group/country')
        ->assertOk()
        ->assertJsonPath('data.group_slug', 'country')
        ->assertJsonPath('data.values.0.name', 'Bangladesh');
});