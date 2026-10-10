<?php

namespace App\Services;

use App\Models\PracticeSession;
use Illuminate\Support\Facades\DB;

/**
 * Rolls finished runs into user_pitch_stats. Recomputes the affected users' rows from
 * note_results, so running it twice gives the same numbers (safe to retry).
 */
class PitchStatsAggregator
{
    /** @return int number of sessions marked as aggregated */
    public function aggregatePending(int $batchSize = 500): int
    {
        $done = 0;

        PracticeSession::query()
            ->whereNotNull('finished_at')
            ->whereNull('aggregated_at')
            ->select(['id', 'user_id'])
            ->chunkById($batchSize, function ($sessions) use (&$done) {
                DB::transaction(function () use ($sessions) {
                    foreach ($sessions->pluck('user_id')->unique() as $userId) {
                        $this->recomputeUser((int) $userId);
                    }
                    PracticeSession::whereIn('id', $sessions->pluck('id'))->update(['aggregated_at' => now()]);
                });
                $done += $sessions->count();
            });

        return $done;
    }

    public function recomputeUser(int $userId): void
    {
        $rows = DB::table('note_results as r')
            ->join('practice_sessions as s', 's.id', '=', 'r.session_id')
            ->where('s.user_id', $userId)
            ->whereNotNull('s.finished_at')
            ->groupBy('r.expected_midi')
            ->selectRaw("r.expected_midi as midi_pitch,
                COUNT(*) as attempts,
                SUM(CASE WHEN r.outcome = 'in_tune' THEN 1 ELSE 0 END) as in_tune,
                AVG(CASE WHEN r.outcome IN ('in_tune', 'sharp', 'flat') THEN r.cents_offset END) as avg_cents")
            ->get();

        $now = now();
        DB::table('user_pitch_stats')->where('user_id', $userId)->delete();
        DB::table('user_pitch_stats')->insert($rows->map(fn ($r) => [
            'user_id' => $userId,
            'midi_pitch' => (int) $r->midi_pitch,
            'attempts' => (int) $r->attempts,
            'in_tune' => (int) $r->in_tune,
            'avg_cents' => $r->avg_cents === null ? null : round((float) $r->avg_cents, 2),
            'updated_at' => $now,
        ])->all());
    }
}
