<?php

namespace Database\Seeders;

use App\Models\Piece;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Bulk data for the EXPLAIN exercise: about a million note_results.
 *   php artisan db:seed --class=LoadTestSeeder
 *   LOADTEST_SESSIONS=5000 php artisan db:seed --class=LoadTestSeeder   (smaller)
 */
class LoadTestSeeder extends Seeder
{
    public function run(): void
    {
        $sessionsWanted = (int) (getenv('LOADTEST_SESSIONS') ?: 30000);
        $users = User::factory()->count(200)->create();
        $pieces = Piece::whereNull('organization_id')->where('parse_status', 'ready')->with('notes')->get();
        if ($pieces->isEmpty()) {
            $this->command?->error('Run the CatalogueSeeder first.');

            return;
        }

        $outcomes = ['in_tune', 'in_tune', 'in_tune', 'sharp', 'flat', 'wrong_note', 'missed'];
        $bar = $this->command?->getOutput()->createProgressBar($sessionsWanted);
        $now = now();

        for ($done = 0; $done < $sessionsWanted; $done += 100) {
            DB::transaction(function () use ($users, $pieces, $outcomes, $now) {
                for ($i = 0; $i < 100; $i++) {
                    $piece = $pieces->random();
                    $finished = $now->copy()->subMinutes(mt_rand(1, 60 * 24 * 365));
                    $sessionId = DB::table('practice_sessions')->insertGetId([
                        'user_id' => $users->random()->id,
                        'piece_id' => $piece->id,
                        'bpm' => $piece->default_bpm,
                        'tolerance_mode' => 'cents',
                        'tolerance_value' => 30,
                        'reference_hz' => 440,
                        'latency_ms' => 0,
                        'started_at' => $finished->copy()->subMinutes(3),
                        'finished_at' => $finished,
                        'score_pct' => mt_rand(3000, 9800) / 100,
                        'created_at' => $finished,
                        'updated_at' => $finished,
                    ]);
                    $rows = [];
                    foreach ($piece->notes as $n) {
                        $v = $outcomes[array_rand($outcomes)];
                        $cents = match ($v) {
                            'in_tune' => mt_rand(-30, 30), 'sharp' => mt_rand(31, 50),
                            'flat' => mt_rand(-50, -31), 'wrong_note' => mt_rand(60, 300), default => null,
                        };
                        $rows[] = [
                            'session_id' => $sessionId, 'note_index' => $n->note_index,
                            'expected_midi' => $n->midi_pitch,
                            'detected_midi' => $cents === null ? null : $n->midi_pitch + (int) round($cents / 100),
                            'detected_hz' => $cents === null ? null : round(440 * 2 ** (($n->midi_pitch - 69 + $cents / 100) / 12), 2),
                            'cents_offset' => $cents, 'outcome' => $v, 'clarity' => $cents === null ? null : 0.95,
                        ];
                    }
                    DB::table('note_results')->insert($rows);
                }
            });
            $bar?->advance(100);
        }
        $bar?->finish();
        $this->command?->newLine();
        $this->command?->info('Done. Now: php artisan practice:aggregate-stats');
    }
}
