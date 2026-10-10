<?php

namespace Database\Factories;

use App\Models\Folder;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Folder> */
class FolderFactory extends Factory
{
    public function definition(): array
    {
        return ['organization_id' => null, 'parent_id' => null, 'name' => fake()->unique()->word()];
    }
}
