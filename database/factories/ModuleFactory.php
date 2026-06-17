<?php

namespace Database\Factories;

use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ModuleFactory extends Factory
{
    protected $model = Module::class;

    public function definition(): array
    {
        $name = $this->faker->word();
        return [
            'uuid' => (string) Str::uuid(),
            'name' => ucfirst($name),
            'slug' => strtolower($name),
            'icon' => 'box',
            'is_active' => true,
        ];
    }
}
