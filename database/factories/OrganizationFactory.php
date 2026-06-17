<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        $name = $this->faker->company();
        return [
            'uuid' => (string) Str::uuid(),
            'company_id' => \App\Models\Company::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'USD',
        ];
    }
}
