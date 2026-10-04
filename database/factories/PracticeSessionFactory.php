<?php

namespace Database\Factories;

use App\Models\Piece;
use App\Models\PracticeSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PracticeSession> */
class PracticeSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'piece_id' => Piece::factory(),
            'bpm' => 80,
            'tolerance_mode' => 'cents',
            'tolerance_value' => 30,
            'reference_hz' => 440,
            'latency_ms' => 0,
            'started_at' => now()->subMinutes(5),
        ];
    }
}
