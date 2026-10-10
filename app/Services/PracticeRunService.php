<?php

namespace App\Services;

use App\Models\NoteResult;
use App\Models\Piece;
use App\Models\PracticeSession;
use App\Models\User;
use App\Support\PitchRule;
use Illuminate\Support\Facades\DB;

/**
 * Stores a run from the browser player. The browser sends what it heard; the outcome of every
 * note is decided here again with the run's own pitch rule, so a client cannot claim "in tune".
 */
class PracticeRunService
{
    public const MAX_RESULTS_PER_BATCH = 2000;

    private const INSERT_CHUNK = 400;

    public function start(User $user, Piece $piece, array $settings): PracticeSession
    {
        return PracticeSession::create([
            'user_id' => $user->id,
            'piece_id' => $piece->id,
            'bpm' => $settings['bpm'],
            'tolerance_mode' => $settings['tolerance_mode'] ?? 'cents',
            'tolerance_value' => $settings['tolerance_value'] ?? 30.0,
            'reference_hz' => $settings['reference_hz'] ?? 440.0,
            'latency_ms' => $settings['latency_ms'] ?? 0,
            'started_at' => now(),
        ]);
    }

    /**
     * One batch in one transaction. A note stored twice for a run violates the unique index and rolls the batch back.
     *
     * @param  list<array{note_index: int, expected_midi: int, detected_hz?: int|float|null, clarity?: int|float|null}>  $heard
     * @return array{results: list<array>, score_pct: float, counts: array<string, int>}
     */
    public function record(PracticeSession $session, array $heard, bool $finished): array
    {
        $rows = array_map(fn (array $note) => $this->judge($session, $note), $heard);

        $summary = DB::transaction(function () use ($session, $rows, $finished) {
            foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
                NoteResult::insert(array_map(fn (array $row) => $row + ['session_id' => $session->id], $chunk));
            }
            $summary = $this->summary($session);
            if ($finished) {
                PracticeSession::whereKey($session->id)->whereNull('finished_at')
                    ->update(['finished_at' => now(), 'score_pct' => $summary['score_pct']]);
            }

            return $summary;
        });

        return $summary + ['results' => array_map(fn (array $row) => [
            'note_index' => $row['note_index'],
            'outcome' => $row['outcome'],
            'cents' => $row['cents_offset'],
            'detected_midi' => $row['detected_midi'],
        ], $rows)];
    }

    private function judge(PracticeSession $session, array $note): array
    {
        $hz = isset($note['detected_hz']) ? (float) $note['detected_hz'] : null;
        $clarity = isset($note['clarity']) ? (float) $note['clarity'] : null;
        $judged = PitchRule::classify(
            $note['expected_midi'], $hz, $clarity,
            $session->tolerance_mode, $session->tolerance_value, $session->reference_hz,
        );

        return [
            'note_index' => $note['note_index'],
            'expected_midi' => $note['expected_midi'],
            'detected_midi' => $judged['detected_midi'],
            'detected_hz' => $judged['outcome'] === PitchRule::MISSED || $hz === null ? null : round($hz, 2),
            'cents_offset' => $judged['cents'],
            'outcome' => $judged['outcome'],
            'clarity' => $clarity === null ? null : round($clarity, 3),
        ];
    }

    /** @return array{score_pct: float, counts: array<string, int>} */
    private function summary(PracticeSession $session): array
    {
        $stored = NoteResult::where('session_id', $session->id)
            ->toBase()->selectRaw('outcome, COUNT(*) AS n')->groupBy('outcome')->pluck('n', 'outcome')
            ->map(fn ($n) => (int) $n);
        $counts = [...array_fill_keys(NoteResult::OUTCOMES, 0), ...$stored];
        $total = array_sum($counts);

        return [
            'score_pct' => $total === 0 ? 0.0 : round(100 * $counts[PitchRule::IN_TUNE] / $total, 2),
            'counts' => $counts,
        ];
    }
}
