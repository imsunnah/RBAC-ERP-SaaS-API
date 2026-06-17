<?php

namespace Database\Factories;

use App\Models\HrmEmployee;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class HrmEmployeeFactory extends Factory
{
    protected $model = HrmEmployee::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'company_id' => \App\Models\Company::factory(),
            'organization_id' => \App\Models\Organization::factory(),
            'user_employee_id' => 'EMP-' . $this->faker->unique()->numberBetween(100000, 999999),
            'biometric_id' => $this->faker->numberBetween(1000, 9999),
            'full_name' => $this->faker->name(),
            'office_email' => $this->faker->unique()->safeEmail(),
            'personal_mobile_no' => $this->faker->phoneNumber(),
            'hire_date' => $this->faker->date(),
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
