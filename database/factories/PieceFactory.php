<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Piece;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Piece> */
class PieceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'organization_id' => null,
            'folder_id' => null,
            'title' => fake()->words(3, true),
            'composer' => fake()->name(),
            'instrument' => 'violin',
            'musicxml_path' => 'pieces/test.musicxml',
            'default_bpm' => 80,
            'beats_per_measure' => 4,
            'note_count' => 0,
            'parse_status' => 'pending',
        ];
    }

    /** In one organization's library (a new organization unless given), so outsiders cannot see it. */
    public function inOrganization(?Organization $organization = null): static
    {
        return $this->state(fn () => ['organization_id' => $organization ?? Organization::factory()]);
    }
}
