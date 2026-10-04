<?php

namespace Database\Factories;

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

    public function catalogue(): static
    {
        return $this->state(['owner_id' => null]);
    }
}
