<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartPracticeSessionRequest;
use App\Http\Requests\StoreNoteResultsRequest;
use App\Models\Piece;
use App\Models\PracticeSession;
use App\Services\PracticeRunService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PracticeSessionController extends Controller
{
    public function __construct(private readonly PracticeRunService $runs) {}

    public function store(StartPracticeSessionRequest $request): JsonResponse
    {
        $piece = Piece::findOrFail($request->integer('piece_id'));
        Gate::authorize('play', $piece);

        $session = $this->runs->start($request->user(), $piece, $request->safe()->except('piece_id'));

        return response()->json($session->only([
            'id', 'piece_id', 'bpm', 'tolerance_mode', 'tolerance_value', 'reference_hz', 'latency_ms', 'started_at',
        ]), 201);
    }

    public function storeResults(StoreNoteResultsRequest $request, PracticeSession $session): JsonResponse
    {
        abort_if($session->finished_at !== null, 409, 'This run is already finished.');

        try {
            $outcome = $this->runs->record($session, $request->validated('results'), $request->boolean('finished'));
        } catch (UniqueConstraintViolationException) {
            abort(409, 'Some of these notes were already stored for this run.');
        }

        return response()->json([
            'session_id' => $session->id,
            'finished' => $request->boolean('finished'),
            'stored' => count($outcome['results']),
            'score_pct' => $outcome['score_pct'],
            'counts' => $outcome['counts'],
            'results' => $outcome['results'],
        ], 201);
    }
}
