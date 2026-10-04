<?php

namespace App\Http\Controllers;

use App\Models\PracticeSession;
use App\Repositories\PracticeStatsRepository;
use App\Services\SessionReportService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PracticeSessionController extends Controller
{
    public function __construct(
        private readonly SessionReportService $reports,
        private readonly PracticeStatsRepository $stats,
    ) {}

    public function show(PracticeSession $session): View
    {
        Gate::authorize('view', $session);
        $session->load('piece');
        $report = $this->reports->build($session);

        return view('sessions.show', [
            'session' => $session,
            'report' => $report,
            // For the browser: the score drawn with this run's verdicts, and the two intonation series.
            'reportData' => [
                'tolerance' => ['mode' => $session->tolerance_mode, 'value' => $session->tolerance_value],
                'scoreUrl' => route('pieces.file', $session->piece),
                'notes' => $report['notes'],
                'pitches' => $report['pitches'],
                'piecePitches' => $this->stats->pitchStatsForPiece($session->user, $session->piece),
            ],
        ]);
    }
}
