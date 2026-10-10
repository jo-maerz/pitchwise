<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Piece;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PieceController extends Controller
{
    public function show(Piece $piece): JsonResponse
    {
        Gate::authorize('play', $piece);

        return response()->json([
            ...$piece->only(['id', 'title', 'composer', 'default_bpm', 'beats_per_measure', 'note_count']),
            'notes' => $piece->notes()->get(['note_index', 'measure', 'midi_pitch', 'onset_beats', 'duration_beats']),
        ])->header('Cache-Control', 'private, max-age=60');
    }
}
