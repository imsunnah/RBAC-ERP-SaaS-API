<?php

namespace Tests\Feature;

use App\Models\GlobalLookup;
use App\Models\HrmEmployee;
use App\Models\HrmHoliday;
use App\Models\HrmLookup;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\SetupOrganizationContext;

class HrmUpgradeTest extends TestCase
{
    use RefreshDatabase, SetupOrganizationContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\App\Http\Middleware\EnforceBillingPlan::class]);
    }

    public function test_can_create_employee_with_nested_relations()
    {
        $org = $this->setupOrgUser('Manager', [
            'hrm.employee.create',
            'hrm.employee.view',
        ]);

        // Create global lookups for gender/marital status/blood group/relationship
        $gender       = GlobalLookup::factory()->create(['name' => 'Male', 'slug' => 'male']);
        $marital      = GlobalLookup::factory()->create(['name' => 'Single', 'slug' => 'single']);
        $blood        = GlobalLookup::factory()->create(['name' => 'O Positive', 'slug' => 'o-positive']);
        $relationship = GlobalLookup::factory()->create(['name' => 'Spouse', 'slug' => 'spouse']);

        $response = $this->actingAsOrgMember($org['user'], $org['org'])
            ->postJson('/api/v1/hrm-employees', [
                'full_name'    => 'Md. Shanto Miah',
                'passport_no'  => 'A700016',
                'nationality'  => 'Bangladeshi',
                'education'    => [
                    [
                        'degree_name'  => 'BSc in CSE',
                        'institution'  => 'Prime University',
                        'passing_year' => 2023,
                        'duration'     => '04 Years',
                    ]
                ],
                'experience'   => [
                    [
                        'company_name' => 'ATI Limited',
                        'designation'  => 'UX Designer',
                        'start_date'   => '2024-01-01',
                        'end_date'     => '2025-06-01',
                        'description'  => 'Worked as UX Designer',
                    ]
                ],
                'family'       => [
                    [
                        'full_name'         => 'Family Contact 1',
                        'gender_id'         => $gender->uuid,
                        'marital_status_id' => $marital->uuid,
                        'blood_group_id'    => $blood->uuid,
                        'relationship_id'   => $relationship->uuid,
                        'nationality'       => 'Bangladeshi',
                    ]
                ]
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.full_name', 'Md. Shanto Miah');
        $response->assertJsonPath('data.passport_no', 'A700016');
        $response->assertJsonPath('data.nationality', 'Bangladeshi');
        $response->assertJsonCount(1, 'data.education');
        $response->assertJsonCount(1, 'data.experience');
        $response->assertJsonCount(1, 'data.family');
        $response->assertJsonPath('data.family.0.relationship.uuid', $relationship->uuid);

        $this->assertDatabaseHas('hrm_employee_education', [
            'degree_name' => 'BSc in CSE',
        ]);
        $this->assertDatabaseHas('hrm_employee_experience', [
            'company_name' => 'ATI Limited',
        ]);
        $this->assertDatabaseHas('hrm_employee_family', [
            'full_name'       => 'Family Contact 1',
            'gender_id'       => $gender->id,
            'marital_status_id' => $marital->id,
            'blood_group_id'  => $blood->id,
            'relationship_id' => $relationship->id,
        ]);
    }

    public function test_stores_family_info_in_database_with_resolved_lookups()
    {
        $org = $this->setupOrgUser('Manager', [
            'hrm.employee.create',
            'hrm.employee.view',
            'hrm.employee.update',
        ]);

        $gender       = GlobalLookup::factory()->create(['name' => 'Female', 'slug' => 'female']);
        $marital      = GlobalLookup::factory()->create(['name' => 'Married', 'slug' => 'married']);
        $blood        = GlobalLookup::factory()->create(['name' => 'A Positive', 'slug' => 'a-positive']);
        $relationship = GlobalLookup::factory()->create(['name' => 'Wife', 'slug' => 'wife']);

        $response = $this->actingAsOrgMember($org['user'], $org['org'])
            ->postJson('/api/v1/hrm-employees', [
                'full_name' => 'John Doe',
                'family'    => [
                    [
                        'full_name'         => 'Jane Doe',
                        'date_of_birth'     => '1995-05-15',
                        'gender_id'         => $gender->uuid,
                        'marital_status_id' => $marital->uuid,
                        'blood_group_id'    => $blood->uuid,
                        'relationship_id'   => $relationship->uuid,
                        'national_id'       => '1234567890',
                        'nationality'       => 'Bangladeshi',
                    ]
                ]
            ]);

        $response->assertStatus(201);

        $employeeUuid = $response->json('data.uuid');
        $employeeId   = \App\Models\HrmEmployee::where('uuid', $employeeUuid)->value('id');

        $this->assertDatabaseHas('hrm_employee_family', [
            'hrm_employee_id'   => $employeeId,
            'full_name'         => 'Jane Doe',
            'date_of_birth'     => '1995-05-15 00:00:00',
            'gender_id'         => $gender->id,
            'marital_status_id' => $marital->id,
            'blood_group_id'    => $blood->id,
            'relationship_id'   => $relationship->id,
            'national_id'       => '1234567890',
            'nationality'       => 'Bangladeshi',
        ]);
    }

    public function test_adds_family_info_on_update_when_created_without_family()
    {
        $org = $this->setupOrgUser('Manager', [
            'hrm.employee.create',
            'hrm.employee.view',
            'hrm.employee.update',
        ]);

        $relationship = GlobalLookup::factory()->create(['name' => 'Spouse', 'slug' => 'spouse']);

        $createResponse = $this->actingAsOrgMember($org['user'], $org['org'])
            ->postJson('/api/v1/hrm-employees', [
                'full_name' => 'No Family Employee',
            ]);

        $createResponse->assertStatus(201);
        $employeeUuid = $createResponse->json('data.uuid');

        $updateResponse = $this->actingAsOrgMember($org['user'], $org['org'])
            ->putJson("/api/v1/hrm-employees/{$employeeUuid}", [
                'full_name' => 'No Family Employee Updated',
                'family'    => [
                    [
                        'full_name'       => 'Family Member 1',
                        'relationship_id' => $relationship->uuid,
                    ]
                ]
            ]);

        $updateResponse->assertStatus(200);
        $updateResponse->assertJsonCount(1, 'data.family');
        $updateResponse->assertJsonPath('data.family.0.full_name', 'Family Member 1');
        $updateResponse->assertJsonPath('data.family.0.relationship.uuid', $relationship->uuid);

        $employeeId = \App\Models\HrmEmployee::where('uuid', $employeeUuid)->value('id');
        $this->assertDatabaseHas('hrm_employee_family', [
            'hrm_employee_id' => $employeeId,
            'full_name'       => 'Family Member 1',
            'relationship_id' => $relationship->id,
        ]);
    }

    public function test_updates_existing_family_and_adds_new_family_on_update()
    {
        $org = $this->setupOrgUser('Manager', [
            'hrm.employee.create',
            'hrm.employee.view',
            'hrm.employee.update',
        ]);

        $relSpouse = GlobalLookup::factory()->create(['name' => 'Spouse 2', 'slug' => 'spouse-2']);
        $relChild  = GlobalLookup::factory()->create(['name' => 'Child', 'slug' => 'child']);

        // 1. Create employee with 1 family member
        $createResponse = $this->actingAsOrgMember($org['user'], $org['org'])
            ->postJson('/api/v1/hrm-employees', [
                'full_name' => 'Multi Family Employee',
                'family'    => [
                    [
                        'full_name'       => 'Original Spouse',
                        'relationship_id' => $relSpouse->uuid,
                    ]
                ]
            ]);

        $createResponse->assertStatus(201);
        $employeeUuid   = $createResponse->json('data.uuid');
        $existingFamUuid = $createResponse->json('data.family.0.uuid');

        // 2. Update existing family member and add a new family member
        $updateResponse = $this->actingAsOrgMember($org['user'], $org['org'])
            ->putJson("/api/v1/hrm-employees/{$employeeUuid}", [
                'full_name' => 'Multi Family Employee',
                'family'    => [
                    [
                        'uuid'            => $existingFamUuid,
                        'full_name'       => 'Updated Spouse Name',
                        'relationship_id' => $relSpouse->uuid,
                    ],
                    [
                        'full_name'       => 'New Child Name',
                        'relationship_id' => $relChild->uuid,
                    ]
                ]
            ]);

        $updateResponse->assertStatus(200);
        $updateResponse->assertJsonCount(2, 'data.family');

        $employeeId = \App\Models\HrmEmployee::where('uuid', $employeeUuid)->value('id');
        $this->assertDatabaseHas('hrm_employee_family', [
            'hrm_employee_id' => $employeeId,
            'uuid'            => $existingFamUuid,
            'full_name'       => 'Updated Spouse Name',
        ]);
        $this->assertDatabaseHas('hrm_employee_family', [
            'hrm_employee_id' => $employeeId,
            'full_name'       => 'New Child Name',
            'relationship_id' => $relChild->id,
        ]);
    }

    public function test_can_store_and_retrieve_emergency_contact_fields()
    {
        $org = $this->setupOrgUser('Manager', [
            'hrm.employee.create',
            'hrm.employee.view',
        ]);

        $rel = HrmLookup::factory()->create([
            'name' => 'Brother',
            'slug' => 'brother',
            'organization_id' => $org['org']->id,
            'company_id' => $org['org']->company_id,
        ]);

        $response = $this->actingAsOrgMember($org['user'], $org['org'])
            ->postJson('/api/v1/hrm-employees', [
                'full_name'          => 'Emg Test Employee',
                'emergency_contacts' => [
                    [
                        'contact_name'             => 'John Contact',
                        'relationship_id'          => $rel->uuid,
                        'contact_number'           => '+8801700000000',
                        'alternate_contact_number' => '+8801800000000',
                        'address'                  => 'Uttara, Dhaka',
                    ]
                ]
            ]);

        $response->assertStatus(201);
        $response->assertJsonCount(1, 'data.emergency_contacts');
        $response->assertJsonPath('data.emergency_contacts.0.contact_name', 'John Contact');
        $response->assertJsonPath('data.emergency_contacts.0.relationship.uuid', $rel->uuid);
        $response->assertJsonPath('data.emergency_contacts.0.contact_number', '+8801700000000');
        $response->assertJsonPath('data.emergency_contacts.0.alternate_contact_number', '+8801800000000');
        $response->assertJsonPath('data.emergency_contacts.0.address', 'Uttara, Dhaka');

        $this->assertDatabaseHas('hrm_employee_emergency_contacts', [
            'contact_name'             => 'John Contact',
            'relationship_id'          => $rel->id,
            'contact_number'           => '+8801700000000',
            'alternate_contact_number' => '+8801800000000',
            'address'                  => 'Uttara, Dhaka',
        ]);
    }

    public function test_can_create_work_shift_with_weekend()
    {
        $org = $this->setupOrgUser('Manager', [
            'hrm.shift.create',
            'hrm.shift.view',
        ]);

        $response = $this->actingAsOrgMember($org['user'], $org['org'])
            ->postJson('/api/v1/work-shifts', [
                'name'       => 'Shift 1',
                'start_time' => '09:00:00',
                'end_time'   => '18:00:00',
                'weekend'    => 'Friday & Saturday',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Shift 1');
        $response->assertJsonPath('data.weekend', 'Friday & Saturday');

        $this->assertDatabaseHas('work_shifts', [
            'name'    => 'Shift 1',
            'weekend' => 'Friday & Saturday',
        ]);
    }

    public function test_can_manage_holidays()
    {
        $org = $this->setupOrgUser('Manager', [
            'hrm.holiday.create',
            'hrm.holiday.view',
            'hrm.holiday.update',
            'hrm.holiday.delete',
        ]);

        $response = $this->actingAsOrgMember($org['user'], $org['org'])
            ->postJson('/api/v1/hrm-holidays', [
                'name' => 'Ashura',
                'date' => '2034-06-22',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Ashura');

        $uuid = $response->json('data.uuid');

        // Update
        $this->actingAsOrgMember($org['user'], $org['org'])
            ->putJson("/api/v1/hrm-holidays/{$uuid}", [
                'name' => 'Ashura Updated',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Ashura Updated');

        // Delete
        $this->actingAsOrgMember($org['user'], $org['org'])
            ->deleteJson("/api/v1/hrm-holidays/{$uuid}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('hrm_holidays', ['uuid' => $uuid]);
    }

    public function test_index_returns_employee_index_resource_format()
    {
        $org = $this->setupOrgUser('Manager', [
            'hrm.employee.view',
        ]);

        $gender = GlobalLookup::factory()->create(['name' => 'Male', 'slug' => 'male']);
        $shift = WorkShift::factory()->create([
            'company_id'      => $org['company']->id,
            'organization_id' => $org['org']->id,
            'name'            => 'Shift 3',
            'start_time'      => '09:00:00',
            'end_time'        => '18:00:00',
            'weekend'         => 'Friday & Saturday',
        ]);

        $employee = HrmEmployee::factory()->create([
            'company_id'       => $org['company']->id,
            'organization_id'  => $org['org']->id,
            'full_name'        => 'Md. Shanto Miah',
            'user_employee_id' => 'ATI-241210-01',
            'work_shift_id'    => $shift->shift_id,
        ]);

        $response = $this->actingAsOrgMember($org['user'], $org['org'])
            ->getJson('/api/v1/hrm-employees');

        $response->assertStatus(200);
        $response->assertJsonPath('data.data.0.id', $employee->uuid);
        $response->assertJsonPath('data.data.0.employeeId', 'ATI-241210-01');
        $response->assertJsonPath('data.data.0.name', 'Md. Shanto Miah');
        $response->assertJsonPath('data.data.0.workShift.name', 'Shift 3');
        $response->assertJsonPath('data.data.0.workShift.time', '9:00 AM–6:00 PM');
        $response->assertJsonPath('data.data.0.workShift.daysOff', 'Friday & Saturday');
    }

    public function test_without_user_endpoint_only_returns_employees_without_user_accounts()
    {
        $org = $this->setupOrgUser('Manager', [
            'hrm.employee.view',
        ]);

        $linkedEmployee = HrmEmployee::factory()->create([
            'company_id'      => $org['company']->id,
            'organization_id' => $org['org']->id,
            'full_name'       => 'Linked Employee',
        ]);
        $unlinkedEmployee = HrmEmployee::factory()->create([
            'company_id'      => $org['company']->id,
            'organization_id' => $org['org']->id,
            'full_name'       => 'Unlinked Employee',
        ]);

        $org['user']->employee_id = $linkedEmployee->id;
        $org['user']->save();

        $response = $this->actingAsOrgMember($org['user'], $org['org'])
            ->getJson('http://localhost/api/v1/hrm-employees/without-user');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.id', $unlinkedEmployee->uuid);
    }

    public function test_can_manage_holidays_by_date()
    {
        $org = $this->setupOrgUser('Manager', [
            'hrm.holiday.create',
            'hrm.holiday.view',
            'hrm.holiday.update',
            'hrm.holiday.delete',
        ]);

        // Create an existing holiday on this date
        $existing = HrmHoliday::factory()->create([
            'company_id'      => $org['company']->id,
            'organization_id' => $org['org']->id,
            'name'            => 'Old Holiday Name',
            'date'            => '2026-05-02',
        ]);

        // POST to by-date endpoint to update existing and add a new one on May 02
        $response = $this->actingAsOrgMember($org['user'], $org['org'])
            ->postJson('/api/v1/hrm-holidays/by-date', [
                'date'     => '2026-05-02',
                'holidays' => [
                    [
                        'uuid' => $existing->uuid,
                        'name' => 'Ashura',
                    ],
                    [
                        'name' => 'July Mass Uprising Day',
                    ]
                ]
            ]);

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.name', 'Ashura');
        $response->assertJsonPath('data.1.name', 'July Mass Uprising Day');

        $this->assertDatabaseHas('hrm_holidays', [
            'uuid' => $existing->uuid,
            'name' => 'Ashura',
        ]);

        $this->assertDatabaseHas('hrm_holidays', [
            'name' => 'July Mass Uprising Day',
            'date' => '2026-05-02',
        ]);

        // Send a request removing Ashura (only July Mass Uprising remains)
        $response2 = $this->actingAsOrgMember($org['user'], $org['org'])
            ->postJson('/api/v1/hrm-holidays/by-date', [
                'date'     => '2026-05-02',
                'holidays' => [
                    [
                        'name' => 'July Mass Uprising Day',
                    ]
                ]
            ]);

        $response2->assertStatus(200);
        $response2->assertJsonCount(1, 'data');
        $this->assertDatabaseMissing('hrm_holidays', [
            'uuid' => $existing->uuid,
        ]);
    }

    public function test_can_filter_holidays_by_year_and_month()
    {
        $org = $this->setupOrgUser('Manager', [
            'hrm.holiday.view',
        ]);

        HrmHoliday::factory()->create([
            'company_id'      => $org['company']->id,
            'organization_id' => $org['org']->id,
            'name'            => 'May Holiday',
            'date'            => '2026-05-15',
        ]);

        HrmHoliday::factory()->create([
            'company_id'      => $org['company']->id,
            'organization_id' => $org['org']->id,
            'name'            => 'June Holiday',
            'date'            => '2026-06-15',
        ]);

        $response = $this->actingAsOrgMember($org['user'], $org['org'])
            ->getJson('/api/v1/hrm-holidays?year=2026&month=5');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'May Holiday');
    }
}
