<?php

namespace App\Services;

use App\Models\PracticeSession;
use App\Support\Pitch;

class SessionReportService
{
    public function build(PracticeSession $session): array
    {
        $session->loadMissing(['piece', 'results']);
        $measures = $session->piece->notes()->pluck('measure', 'note_index');

        $counts = array_fill_keys(['in_tune', 'sharp', 'flat', 'wrong_note', 'missed'], 0);
        $byMeasure = [];
        $byPitch = [];
        $notes = [];

        foreach ($session->results as $r) {
            $counts[$r->outcome]++;
            $measure = (int) ($measures[$r->note_index] ?? 0);
            $byMeasure[$measure] ??= ['measure' => $measure, 'notes' => 0, 'in_tune' => 0];
            $byMeasure[$measure]['notes']++;
            $byMeasure[$measure]['in_tune'] += $r->outcome === 'in_tune' ? 1 : 0;

            $p = &$byPitch[$r->expected_midi];
            $p ??= ['attempts' => 0, 'in_tune' => 0, 'cents' => []];
            $p['attempts']++;
            $p['in_tune'] += $r->outcome === 'in_tune' ? 1 : 0;
            if (in_array($r->outcome, ['in_tune', 'sharp', 'flat'], true) && $r->cents_offset !== null) {
                $p['cents'][] = $r->cents_offset;
            }
            unset($p);

            $notes[] = [
                'index' => $r->note_index,
                'measure' => $measure,
                'expected' => Pitch::name($r->expected_midi),
                'detected' => $r->detected_midi !== null ? Pitch::name($r->detected_midi) : null,
                'hz' => $r->detected_hz,
                'cents' => $r->cents_offset,
                'outcome' => $r->outcome,
            ];
        }

        ksort($byPitch);
        $pitches = collect($byPitch)->map(fn ($p, $midi) => Pitch::intonationByNoteBar(
            (int) $midi,
            $p['attempts'],
            $p['in_tune'],
            $p['cents'] ? round(array_sum($p['cents']) / count($p['cents']), 2) : null,
        ))->values()->all();

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
        $pitched = $session->results->whereIn('outcome', ['in_tune', 'sharp', 'flat'])->whereNotNull('cents_offset');

        return [
            'score' => $session->score_pct,
            'total' => $total,
            'checked_of' => $session->piece->note_count,
            'counts' => $counts,
            'avg_cents' => $pitched->isEmpty() ? null : round($pitched->avg('cents_offset'), 1),
            'measures' => array_values($byMeasure),
            'weakest' => $weakest,
            'notes' => $notes,
            'pitches' => $pitches,
        ];
    }
}
