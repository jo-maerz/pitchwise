<?php

namespace App\Http\Controllers;

use App\Models\PracticeSession;
use App\Services\SessionReportService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PracticeSessionController extends Controller
{
    public function __construct(private readonly SessionReportService $reports) {}

    public function show(PracticeSession $session): View
    {
        Gate::authorize('view', $session);
        $session->load('piece');

        return view('sessions.show', [
            'session' => $session,
            'report' => $this->reports->build($session),
        ]);
    }
}
