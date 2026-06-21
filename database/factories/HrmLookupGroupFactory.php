<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class HrmLookupGroupFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->word();
        return [
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'organization_id' => Organization::factory(),
            'company_id' => Company::factory(),
            'is_active' => true,
        ];
    }
}
