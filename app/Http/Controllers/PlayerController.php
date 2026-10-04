<?php

namespace App\Http\Controllers;

use App\Models\Piece;
use App\Services\PlayerTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PlayerController extends Controller
{
    public function __construct(private readonly PlayerTokenService $tokens) {}

    public function show(Request $request, Piece $piece): View
    {
        Gate::authorize('play', $piece);

        return view('player.show', [
            'piece' => $piece,
            'config' => [
                'pieceId' => $piece->id,
                'title' => $piece->title,
                'scoreUrl' => route('pieces.file', $piece),
                'apiUrl' => rtrim(config('practice.api_url'), '/'),
                'token' => $this->tokens->issue($request->user()),
                'reportUrl' => url('/sessions'),
                'defaults' => [
                    'bpm' => $piece->default_bpm,
                    'beatsPerMeasure' => $piece->beats_per_measure,
                    'toleranceMode' => config('practice.tolerance.mode'),
                    'toleranceValue' => config('practice.tolerance.value'),
                    'referenceHz' => config('practice.reference_hz'),
                ],
            ],
        ]);
    }

    public function tuner(): View
    {
        return view('player.tuner', [
            'config' => ['defaults' => [
                'referenceHz' => config('practice.reference_hz'),
                'toleranceMode' => config('practice.tolerance.mode'),
                'toleranceValue' => config('practice.tolerance.value'),
            ]],
        ]);
    }
}
