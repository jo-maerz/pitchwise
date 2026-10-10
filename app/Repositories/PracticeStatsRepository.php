<?php

namespace App\Repositories;

use App\Models\Piece;
use App\Models\PracticeSession;
use App\Models\User;
use App\Models\UserPitchStat;
use App\Support\Pitch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PracticeStatsRepository
{
    public function scoreHistory(User $user, int $limit = 60): Collection
    {
        return PracticeSession::query()
            ->select(['id', 'piece_id', 'finished_at', 'score_pct', 'tolerance_mode', 'tolerance_value'])
            ->with('piece:id,title')
            ->where('user_id', $user->id)
            ->whereNotNull('finished_at')
            ->orderByDesc('finished_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    public function recentSessions(User $user, int $limit = 10): Collection
    {
        return PracticeSession::query()
            ->with('piece:id,title')
            ->where('user_id', $user->id)
            ->whereNotNull('finished_at')
            ->orderByDesc('finished_at')
            ->limit($limit)
            ->get();
    }

    /** Per-pitch intonation, written by the practice:aggregate-stats command. */
    public function pitchStats(User $user): Collection
    {
        return UserPitchStat::query()
            ->where('user_id', $user->id)
            ->where('attempts', '>', 0)
            ->orderBy('midi_pitch')
            ->get();
    }

    /**
     * Computed on demand: the (user_id, piece_id, finished_at) index narrows the runs first.
     */
    public function pitchStatsForPiece(User $user, Piece $piece): Collection
    {
        return DB::table('note_results as r')
            ->join('practice_sessions as s', 's.id', '=', 'r.session_id')
            ->where('s.user_id', $user->id)
            ->where('s.piece_id', $piece->id)
            ->whereNotNull('s.finished_at')
            ->groupBy('r.expected_midi')
            ->orderBy('r.expected_midi')
            ->selectRaw("r.expected_midi as midi, COUNT(*) as attempts,
                SUM(CASE WHEN r.outcome = 'in_tune' THEN 1 ELSE 0 END) as in_tune,
                AVG(CASE WHEN r.outcome IN ('in_tune', 'sharp', 'flat') THEN r.cents_offset END) as avg_cents")
            ->get()
            ->map(fn ($r) => Pitch::intonationByNoteBar(
                (int) $r->midi,
                (int) $r->attempts,
                (int) $r->in_tune,
                $r->avg_cents === null ? null : round((float) $r->avg_cents, 2),
            ))
            ->values();
    }

    /**
     * @return Collection<int, object{piece_id:int, title:string, measure:int, notes:int, in_tune:int, rate:float}>
     */
    public function troubleMeasures(User $user, int $recentRuns = 20, int $limit = 5): Collection
    {
        $sessionIds = PracticeSession::query()
            ->where('user_id', $user->id)
            ->whereNotNull('finished_at')
            ->orderByDesc('finished_at')
            ->limit($recentRuns)
            ->pluck('id');

        if ($sessionIds->isEmpty()) {
            return collect();
        }

        return DB::table('note_results as r')
            ->join('practice_sessions as s', 's.id', '=', 'r.session_id')
            ->join('piece_notes as n', function ($join) {
                $join->on('n.piece_id', '=', 's.piece_id')->on('n.note_index', '=', 'r.note_index');
            })
            ->join('pieces as p', 'p.id', '=', 's.piece_id')
            ->whereIn('r.session_id', $sessionIds)
            ->groupBy('s.piece_id', 'p.title', 'n.measure')
            ->havingRaw('COUNT(*) >= 3')
            ->selectRaw("s.piece_id, p.title, n.measure, COUNT(*) as notes, SUM(CASE WHEN r.outcome = 'in_tune' THEN 1 ELSE 0 END) as in_tune")
            ->orderByRaw("SUM(CASE WHEN r.outcome = 'in_tune' THEN 1 ELSE 0 END) * 1.0 / COUNT(*)")
            ->orderByDesc('notes')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $row->rate = $row->notes > 0 ? round(100 * $row->in_tune / $row->notes, 1) : 0.0;

                return $row;
            });
    }
}
