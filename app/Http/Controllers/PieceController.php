<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePieceRequest;
use App\Http\Requests\UpdatePieceRequest;
use App\Models\Piece;
use App\Repositories\PieceRepository;
use App\Repositories\PracticeStatsRepository;
use App\Services\PieceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PieceController extends Controller
{
    public function __construct(
        private readonly PieceService $service,
        private readonly PieceRepository $pieces,
        private readonly PracticeStatsRepository $stats,
    ) {}

    public function index(Request $request): View
    {
        return view('pieces.index', ['pieces' => $this->pieces->paginateVisibleTo($request->user())]);
    }

    public function create(): View
    {
        return view('pieces.create');
    }

    public function store(StorePieceRequest $request): RedirectResponse
    {
        $piece = $this->service->upload($request->user(), $request->file('score'), $request->validated(), $request->file('pdf'));

        return redirect()->route('pieces.show', $piece)
            ->with('status', $piece->musicxml_path === null
                ? 'PDF uploaded. Recognising the notes now; this can take a few minutes. You can already practise with the PDF and the tuner.'
                : 'Score uploaded. Reading the notes now; this takes a few seconds.');
    }

    public function show(Request $request, Piece $piece): View
    {
        Gate::authorize('view', $piece);

        $runs = $piece->sessions()
            ->where('user_id', $request->user()->id)
            ->whereNotNull('finished_at')
            ->latest('finished_at')
            ->limit(10)
            ->get();

        $pitches = $this->stats->pitchStatsForPiece($request->user(), $piece);

        return view('pieces.show', compact('piece', 'runs', 'pitches'));
    }

    /** The MusicXML itself, for the score renderer on the player page. */
    public function file(Piece $piece): StreamedResponse
    {
        Gate::authorize('view', $piece);
        abort_if($piece->musicxml_path === null, 404);

        return Storage::disk('local')->response($piece->musicxml_path, null, [
            'Content-Type' => 'application/vnd.recordare.musicxml',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }

    /** The owner has checked the recognised score against their PDF: open it for practice. */
    public function confirm(Piece $piece): RedirectResponse
    {
        Gate::authorize('update', $piece);
        abort_unless($piece->needsReview(), 409);
        $this->service->confirmRecognition($piece);

        return redirect()->route('pieces.show', $piece)->with('status', 'Score confirmed. Ready to practise.');
    }

    /** The original PDF, for the PDF page's viewer. */
    public function pdf(Piece $piece): StreamedResponse
    {
        Gate::authorize('playPdf', $piece);

        return Storage::disk('local')->response($piece->source_pdf_path, null, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }

    /** The owner prefers the PDF with the tuner alone to a recognised score they cannot trust (or none was found). */
    public function usePdf(Piece $piece): RedirectResponse
    {
        Gate::authorize('update', $piece);
        abort_unless($piece->hasPdf() && in_array($piece->parse_status, ['needs_review', 'failed'], true), 409);
        $this->service->usePdfOnly($piece);

        return redirect()->route('pieces.show', $piece)->with('status', 'Using the PDF with the tuner. Upload a MusicXML file any time to get note-by-note checking.');
    }

    public function edit(Piece $piece): View
    {
        Gate::authorize('update', $piece);

        return view('pieces.edit', compact('piece'));
    }

    public function update(UpdatePieceRequest $request, Piece $piece): RedirectResponse
    {
        $this->service->update($piece, $request->validated(), $request->file('score'), $request->file('pdf'));

        return redirect()->route('pieces.show', $piece)->with('status', ($request->hasFile('score') || $request->hasFile('pdf'))
            ? 'Piece updated. Reading the new score now; this takes a few seconds.'
            : 'Piece updated.');
    }

    public function destroy(Piece $piece): RedirectResponse
    {
        Gate::authorize('delete', $piece);
        $this->service->delete($piece);

        return redirect()->route('pieces.index')->with('status', 'Piece deleted.');
    }
}
