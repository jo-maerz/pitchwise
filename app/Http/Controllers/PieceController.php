<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePieceRequest;
use App\Http\Requests\UpdatePieceRequest;
use App\Models\Piece;
use App\Repositories\PieceRepository;
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
        $piece = $this->service->upload($request->user(), $request->file('score'), $request->validated());

        return redirect()->route('pieces.show', $piece)
            ->with('status', 'Score uploaded. Reading the notes now; this takes a few seconds.');
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

        return view('pieces.show', compact('piece', 'runs'));
    }

    /** The MusicXML itself, for the score renderer on the player page. */
    public function file(Piece $piece): StreamedResponse
    {
        Gate::authorize('view', $piece);

        return Storage::disk('local')->response($piece->musicxml_path, null, [
            'Content-Type' => 'application/vnd.recordare.musicxml',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }

    public function edit(Piece $piece): View
    {
        Gate::authorize('update', $piece);

        return view('pieces.edit', compact('piece'));
    }

    public function update(UpdatePieceRequest $request, Piece $piece): RedirectResponse
    {
        $this->service->update($piece, $request->validated(), $request->file('score'));

        return redirect()->route('pieces.show', $piece)->with('status', $request->hasFile('score')
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
