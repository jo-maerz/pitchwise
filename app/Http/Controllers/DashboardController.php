<?php

namespace App\Http\Controllers;

use App\Repositories\PracticeStatsRepository;
use App\Support\Pitch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly PracticeStatsRepository $stats) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $history = $this->stats->scoreHistory($user);
        $pitches = $this->stats->pitchStats($user);

        return view('dashboard', [
            'recent' => $this->stats->recentSessions($user),
            'trouble' => $this->stats->troubleMeasures($user),
            'pitches' => $pitches,
            'chartData' => [
                'history' => $history->map(fn ($s) => [
                    'id' => $s->id,
                    'date' => $s->finished_at->toIso8601String(),
                    'score' => $s->score_pct,
                    'piece' => $s->piece?->title,
                ])->values(),
                'pitches' => $pitches->map(fn ($p) => Pitch::intonationByNoteBar($p->midi_pitch, $p->attempts, $p->in_tune, $p->avg_cents))->values(),
            ],
        ]);
    }
}
