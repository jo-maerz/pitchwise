<?php

namespace App\Http\Controllers;

use App\Models\Piece;
use App\Models\User;
use App\Services\PlayerTokenService;
use App\Support\Instruments;
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
                'apiUrl' => url('api/v1'),
                'token' => $this->tokens->issue($request->user()),
                'reportUrl' => url('/sessions'),
                // A piece with a PDF is annotated on the PDF, which this player does not show.
                'annotations' => $piece->hasPdf() ? null : $this->annotations($piece, $request->user()),
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

    /** Original PDF page plus the live tuner. No notes are followed: the nearest note is taken as the target. */
    public function pdf(Request $request, Piece $piece): View
    {
        Gate::authorize('playPdf', $piece);

        return view('player.pdf', [
            'piece' => $piece,
            'config' => [
                'pdfUrl' => route('pieces.pdf', $piece),
                'annotations' => $this->annotations($piece, $request->user()),
                'defaults' => [
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
            'config' => [
                'defaults' => [
                    'referenceHz' => config('practice.reference_hz'),
                    'toleranceMode' => config('practice.tolerance.mode'),
                    'toleranceValue' => config('practice.tolerance.value'),
                ],
                'instruments' => Instruments::forTuner(),
            ],
        ]);
    }

    /** The layers this user sees on the score, the organization's shared one below their own; null without any. */
    private function annotations(Piece $piece, User $user): ?array
    {
        $annotations = $piece->annotations()
            ->where(fn ($query) => $query->where('user_id', $user->id)
                ->when($piece->organization_id !== null, fn ($query) => $query->orWhereNull('user_id')))
            ->get()
            ->sortBy(fn ($annotation) => $annotation->user_id !== null)
            ->filter(fn ($annotation) => collect($annotation->pages)->flatten(1)->isNotEmpty())
            ->values();

        if ($annotations->isEmpty()) {
            return null;
        }

        return [
            'layers' => $annotations->pluck('pages')->all(),
            'outdated' => $annotations->contains(fn ($annotation) => $annotation->source_path !== $piece->annotationSourcePath()),
        ];
    }
}
