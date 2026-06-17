<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Organization;
use App\Models\HrmAttendance;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Schema;

class HrmAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected $org;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([\App\Http\Middleware\EnforceBillingPlan::class]);

        $company = \App\Models\Company::factory()->create();

        $this->org = Organization::create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Test Org',
            'slug' => 'test-org-' . Str::random(5),
            'status' => 'active',
            'company_id' => $company->id,
        ]);

        $this->user = User::factory()->create();

        $this->user->organizations()->attach($this->org->id, [
            'is_owner' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->user->is_super_admin = true;
        $this->user->save();

        Passport::actingAs($this->user);
    }

    public function test_can_list_attendance_records()
    {
        HrmAttendance::create([
            'uuid' => Str::uuid()->toString(),
            'company_id' => $this->org->company_id,
            'organization_id' => $this->org->id,
            'device_user_id' => '101',
            'date' => now()->toDateString(),
            'punch_in' => now(),
            'punch_state' => 1,
            'punch_type' => 1,
        ]);

        $response = $this->getJson('/api/v1/attendance/hrm', [
            'X-ORG-ID' => $this->org->uuid,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['data']])
            ->assertJsonCount(1, 'data.data');
    }
}
