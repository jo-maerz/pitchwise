<?php

namespace Database\Seeders;

use App\Models\Piece;
use App\Models\PracticeSession;
use App\Models\User;
use App\Services\PitchStatsAggregator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PracticeApi\Verdict;

/**
 * Fake but plausible practice history for the demo user, so the dashboard has something to show.
 * The simulated player plays C♯ and F♯ sharp (a classic first-finger-high habit) and improves over time.
 */
class DemoHistorySeeder extends Seeder
{
    public function run(PitchStatsAggregator $aggregator): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();
        if ($user->practiceSessions()->exists()) {
            return;
        }
        mt_srand(7);

        $pieces = Piece::whereNull('owner_id')->with('notes')->get();
        $runs = 14;
        for ($run = 0; $run < $runs; $run++) {
            $piece = $pieces[$run % $pieces->count()];
            $skill = $run / $runs; // 0 → 1
            $finishedAt = now()->subDays(($runs - $run) * 2)->setTime(19, 30)->addMinutes(mt_rand(0, 90));

            $session = PracticeSession::create([
                'user_id' => $user->id,
                'piece_id' => $piece->id,
                'bpm' => $piece->default_bpm,
                'tolerance_mode' => 'cents',
                'tolerance_value' => 30,
                'reference_hz' => 440,
                'latency_ms' => 80,
                'started_at' => $finishedAt->copy()->subMinutes(2),
                'finished_at' => $finishedAt,
            ]);

            $rows = [];
            $inTune = 0;
            foreach ($piece->notes as $note) {
                $pc = $note->midi_pitch % 12;
                $bias = in_array($pc, [1, 6], true) ? 32 - 18 * $skill : 0;   // C♯, F♯ sharp
                $bias += $note->midi_pitch >= 76 ? -12 : 0;                    // high notes a bit flat
                $spread = 26 - 12 * $skill;
                $cents = $bias + $this->gauss() * $spread;
                $missed = mt_rand(1, 100) <= 3;
                $hz = $missed ? null : Verdict::expectedHz($note->midi_pitch) * 2 ** ($cents / 1200);
                $j = Verdict::judge($note->midi_pitch, $hz, $missed ? null : 0.95);
                $inTune += $j['verdict'] === Verdict::IN_TUNE ? 1 : 0;
                $rows[] = [
                    'session_id' => $session->id,
                    'note_index' => $note->note_index,
                    'expected_midi' => $note->midi_pitch,
                    'detected_midi' => $j['detected_midi'],
                    'detected_hz' => $hz === null ? null : round($hz, 2),
                    'cents_offset' => $j['cents'],
                    'verdict' => $j['verdict'],
                    'clarity' => $missed ? null : 0.95,
                ];
            }
            DB::table('note_results')->insert($rows);
            $session->update(['score_pct' => round(100 * $inTune / count($rows), 2)]);
        }

        $aggregator->aggregatePending();
    }

    private function gauss(): float
    {
        $u = max(mt_rand() / mt_getrandmax(), 1e-9);
        $v = mt_rand() / mt_getrandmax();

        return sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
    }
}
