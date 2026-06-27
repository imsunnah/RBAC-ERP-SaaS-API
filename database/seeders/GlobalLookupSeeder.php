<?php

namespace Database\Seeders;

use App\Models\GlobalLookup;
use App\Models\GlobalLookupGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GlobalLookupSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'Gender' => [
                ['name' => 'Male', 'slug' => 'male'],
                ['name' => 'Female', 'slug' => 'female'],
                ['name' => 'Other', 'slug' => 'other'],
            ],
            'Religion' => [
                ['name' => 'Islam', 'slug' => 'islam'],
                ['name' => 'Hinduism', 'slug' => 'hinduism'],
                ['name' => 'Christianity', 'slug' => 'christianity'],
                ['name' => 'Buddhism', 'slug' => 'buddhism'],
                ['name' => 'Other', 'slug' => 'other-religion'],
            ],
            'Marital Status' => [
                ['name' => 'Single', 'slug' => 'single'],
                ['name' => 'Married', 'slug' => 'married'],
                ['name' => 'Divorced', 'slug' => 'divorced'],
                ['name' => 'Widowed', 'slug' => 'widowed'],
            ],
        ];

        foreach ($data as $groupName => $items) {
            $group = GlobalLookupGroup::firstOrCreate(
                ['slug' => Str::slug($groupName)],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $groupName,
                    'is_active' => true,
                ]
            );

            foreach ($items as $item) {
                GlobalLookup::firstOrCreate(
                    [
                        'global_lookup_group_id' => $group->id,
                        'slug' => $item['slug'],
                    ],
                    [
                        'uuid' => (string) Str::uuid(),
                        'name' => $item['name'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
