<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePieceRequest;
use App\Http\Requests\UpdatePieceRequest;
use App\Models\Folder;
use App\Models\Piece;
use App\Models\User;
use App\Repositories\PieceRepository;
use App\Repositories\PracticeStatsRepository;
use App\Services\LibraryService;
use App\Services\PieceService;
use App\Support\Instruments;
use App\Support\LibraryLocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        private readonly LibraryService $library,
    ) {}

    /** Top level: every library the user can see, each with its top-level folders and loose pieces. */
    public function index(Request $request): View
    {
        $user = $request->user();
        $instrument = $this->instrumentFilter($request);
        $sections = $this->library->librariesFor($user)->map(fn (array $library) => $library + [
            'location' => new LibraryLocation($library['organization_id']),
            'folders' => $this->folders(Folder::inLibrary($library['organization_id'])->whereNull('parent_id'), $library['organization_id'], $instrument),
            'pieces' => $this->pieces->allIn($user, $library['organization_id'], null, $instrument),
        ]);

        return view('pieces.index', $this->listing($user, null, $sections, $instrument));
    }

    public function folder(Request $request, Folder $folder): View
    {
        Gate::authorize('view', $folder);
        $instrument = $this->instrumentFilter($request);

        $sections = collect([[
            'organization_id' => $folder->organization_id,
            'name' => $this->library->libraryName($folder->organization_id),
            'location' => new LibraryLocation($folder->organization_id, $folder->id),
            'folders' => $this->folders($folder->children(), $folder->organization_id, $instrument),
            'pieces' => $this->pieces->paginateIn($request->user(), $folder->organization_id, $folder->id, $instrument),
        ]]);

        return view('pieces.index', $this->listing($request->user(), $folder, $sections, $instrument));
    }

    private function instrumentFilter(Request $request): ?string
    {
        $instrument = $request->query('instrument');

        return is_string($instrument) && in_array($instrument, Instruments::keys(), true) ? $instrument : null;
    }

    /** Folders with their counts; with an instrument filter, only those holding pieces for it. */
    private function folders(Builder|HasMany $query, ?int $organizationId, ?string $instrument): Collection
    {
        $folders = $query->withCount(['children', 'pieces' => fn ($q) => $q->when($instrument, fn ($q) => $q->where('instrument', $instrument))])
            ->orderBy('name')
            ->get();
        if ($instrument === null) {
            return $folders;
        }
        $keep = $this->library->foldersWithInstrument($organizationId, $instrument);

        return $folders->filter(fn (Folder $f) => isset($keep[$f->id]))->values();
    }

    private function listing(User $user, ?Folder $folder, Collection $sections, ?string $instrument): array
    {
        return [
            'folder' => $folder,
            'sections' => $sections,
            'instrument' => $instrument,
            'instrumentOptions' => Instruments::grouped($this->library->instrumentsVisibleTo($user)),
        ];
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Piece::class);

        return view('pieces.create', [
            'locations' => $this->library->locationsFor($request->user()),
            'selected' => $request->query('location'),
        ]);
    }

    public function store(StorePieceRequest $request): RedirectResponse
    {
        $piece = $this->service->upload($request->user(), $request->location(), $request->file('score'), $request->validated(), $request->file('pdf'));

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

    public function edit(Request $request, Piece $piece): View
    {
        Gate::authorize('update', $piece);

        return view('pieces.edit', [
            'piece' => $piece,
            'locations' => $this->library->locationsFor($request->user()),
            'selected' => (new LibraryLocation($piece->organization_id, $piece->folder_id))->key(),
        ]);
    }

    public function update(UpdatePieceRequest $request, Piece $piece): RedirectResponse
    {
        $this->service->update($piece, $request->validated(), $request->location(), $request->file('score'), $request->file('pdf'));

        return redirect()->route('pieces.show', $piece)->with('status', ($request->hasFile('score') || $request->hasFile('pdf'))
            ? 'Piece updated. Reading the new score now; this takes a few seconds.'
            : 'Piece updated.');
    }

    public function destroy(Piece $piece): RedirectResponse
    {
        Gate::authorize('delete', $piece);
        $folderId = $piece->folder_id;
        $this->service->delete($piece);

        return redirect($folderId ? route('folders.show', $folderId) : route('pieces.index'))->with('status', 'Piece deleted.');
    }
}
