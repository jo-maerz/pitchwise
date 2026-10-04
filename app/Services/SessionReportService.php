<?php

namespace App\Services;

use App\Models\PracticeSession;
use App\Support\Pitch;

/** Builds the end-of-run report shown on the session page. */
class SessionReportService
{
    public function build(PracticeSession $session): array
    {
        $session->loadMissing(['piece', 'results']);
        $measures = $session->piece->notes()->pluck('measure', 'note_index');

        $counts = array_fill_keys(['in_tune', 'sharp', 'flat', 'wrong_note', 'missed'], 0);
        $byMeasure = [];
        $notes = [];

        foreach ($session->results as $r) {
            $counts[$r->verdict]++;
            $measure = (int) ($measures[$r->note_index] ?? 0);
            $byMeasure[$measure] ??= ['measure' => $measure, 'notes' => 0, 'in_tune' => 0];
            $byMeasure[$measure]['notes']++;
            $byMeasure[$measure]['in_tune'] += $r->verdict === 'in_tune' ? 1 : 0;

            $notes[] = [
                'index' => $r->note_index,
                'measure' => $measure,
                'expected' => Pitch::name($r->expected_midi),
                'detected' => $r->detected_midi !== null ? Pitch::name($r->detected_midi) : null,
                'hz' => $r->detected_hz,
                'cents' => $r->cents_offset,
                'verdict' => $r->verdict,
            ];
        }

        foreach ($byMeasure as &$m) {
            $m['rate'] = round(100 * $m['in_tune'] / max(1, $m['notes']), 1);
        }
        unset($m);
        ksort($byMeasure);

        $weakest = collect($byMeasure)
            ->filter(fn ($m) => $m['rate'] < 100)
            ->sortBy([['rate', 'asc'], ['notes', 'desc']])
            ->take(3)
            ->values()
            ->all();

        $total = array_sum($counts);
        $pitched = $session->results->whereIn('verdict', ['in_tune', 'sharp', 'flat'])->whereNotNull('cents_offset');

        return [
            'score' => $session->score_pct,
            'total' => $total,
            'checked_of' => $session->piece->note_count,
            'counts' => $counts,
            'avg_cents' => $pitched->isEmpty() ? null : round($pitched->avg('cents_offset'), 1),
            'measures' => array_values($byMeasure),
            'weakest' => $weakest,
            'notes' => $notes,
        ];
    }
}
